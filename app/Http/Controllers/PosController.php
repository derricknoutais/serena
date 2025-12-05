<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Requests\StoreCounterSaleRequest;
use App\Http\Requests\StoreRoomSaleRequest;
use App\Models\Folio;
use App\Models\Hotel;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Reservation;
use App\Services\FolioBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    use ResolvesActiveHotel;

    public function __construct(private readonly FolioBillingService $folioBilling)
    {
    }

    public function index(Request $request): Response
    {
        $hotel = $this->requireActiveHotel($request);
        $tenantId = $request->user()->tenant_id;

        $categories = ProductCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn(ProductCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
            ])
            ->values();

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->with(['category:id,name', 'tax:id,name,rate'])
            ->orderBy('name')
            ->get()
            ->map(fn(Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'unit_price' => (float) $product->unit_price,
                'category_id' => $product->product_category_id,
                'category_name' => $product->category?->name,
                'tax_name' => $product->tax?->name,
                'tax_rate' => (float) ($product->tax?->rate ?? 0),
                'account_code' => $product->account_code,
            ])
            ->values();

        $paymentMethods = PaymentMethod::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) use ($hotel): void {
                $query->whereNull('hotel_id')
                    ->orWhere('hotel_id', $hotel->id);
            })
            ->orderBy('name')
            ->get()
            ->map(fn(PaymentMethod $method) => [
                'id' => $method->id,
                'name' => $method->name,
                'code' => $method->code,
                'type' => $method->type,
            ])
            ->values();

        $inHouseReservations = Reservation::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotel->id)
            ->where('status', Reservation::STATUS_IN_HOUSE)
            ->with(['guest:id,first_name,last_name', 'room:id,number'])
            ->orderBy('code')
            ->get()
            ->map(fn(Reservation $reservation) => [
                'id' => $reservation->id,
                'code' => $reservation->code,
                'guest_name' => $reservation->guest?->full_name,
                'room_number' => $reservation->room?->number,
                'check_out_date' => $reservation->check_out_date?->toDateString(),
            ])
            ->values();

        return Inertia::render('Pos/Index', [
            'categories' => $categories,
            'products' => $products,
            'paymentMethods' => $paymentMethods,
            'inHouseReservations' => $inHouseReservations,
            'currency' => $hotel->currency,
        ]);
    }

    public function storeCounterSale(StoreCounterSaleRequest $request): JsonResponse
    {
        $hotel = $this->requireActiveHotel($request);
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $data = $request->validated();

        $products = $this->productsForItems($data['items'], $tenantId, $hotel->id);
        $lines = $this->normalizeItems($data['items'], $products);
        $totalAmount = collect($lines)->sum(fn(array $line) => $line['total_amount']);

        $paymentMethod = PaymentMethod::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($hotel): void {
                $query->whereNull('hotel_id')
                    ->orWhere('hotel_id', $hotel->id);
            })
            ->findOrFail($data['payment_method_id']);

        $folio = DB::transaction(function () use ($hotel, $user, $lines, $totalAmount, $paymentMethod, $data): Folio {
            $label = $data['client_label'] ?? 'Vente comptoir';

            $folio = Folio::query()->create([
                'tenant_id' => $user->tenant_id,
                'hotel_id' => $hotel->id,
                'code' => $this->generateFolioCode(),
                'status' => 'open',
                'is_main' => false,
                'type' => 'pos',
                'origin' => 'pos_bar',
                'currency' => $hotel->currency ?? 'XAF',
                'billing_name' => $label,
                'opened_at' => now(),
            ]);

            foreach ($lines as $line) {
                /** @var \App\Models\Product $product */
                $product = $line['product'];

                $folio->addCharge([
                    'product_id' => $product->id,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_amount' => $line['tax_amount'],
                    'total_amount' => $line['total_amount'],
                    'type' => 'bar',
                    'account_code' => $product->account_code,
                    'date' => now()->toDateString(),
                ]);
            }

            $cashSessionId = null;
            if ($paymentMethod->type === 'cash') {
                $activeSession = \App\Models\CashSession::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('hotel_id', $hotel->id)
                    ->where('type', 'bar')
                    ->where('status', 'open')
                    ->first();

                if (!$activeSession) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'payment_method_id' => 'Aucune caisse bar ouverte. Veuillez ouvrir une session de caisse.',
                    ]);
                }
                $cashSessionId = $activeSession->id;
            }

            $folio->addPayment([
                'amount' => $totalAmount,
                'currency' => $hotel->currency ?? 'XAF',
                'payment_method_id' => $paymentMethod->id,
                'paid_at' => now(),
                'notes' => $label,
                'created_by_user_id' => $user->id,
                'cash_session_id' => $cashSessionId,
            ]);

            return $folio;
        });

        return response()->json([
            'success' => true,
            'folio_id' => $folio->id,
            'total' => $totalAmount,
            'currency' => $folio->currency,
        ], 201);
    }

    public function storeRoomSale(StoreRoomSaleRequest $request): JsonResponse
    {
        $hotel = $this->requireActiveHotel($request);
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $data = $request->validated();

        $reservation = Reservation::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotel->id)
            ->where('status', Reservation::STATUS_IN_HOUSE)
            ->findOrFail($data['reservation_id']);

        $products = $this->productsForItems($data['items'], $tenantId, $hotel->id);
        $lines = $this->normalizeItems($data['items'], $products);
        $addedTotal = collect($lines)->sum(fn(array $line) => $line['total_amount']);

        $folio = DB::transaction(function () use ($reservation, $lines) {
            $folio = $this->folioBilling->ensureMainFolioForReservation($reservation);

            foreach ($lines as $line) {
                /** @var \App\Models\Product $product */
                $product = $line['product'];

                $folio->addCharge([
                    'product_id' => $product->id,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_amount' => $line['tax_amount'],
                    'total_amount' => $line['total_amount'],
                    'type' => 'bar',
                    'account_code' => $product->account_code,
                    'date' => now()->toDateString(),
                ]);
            }

            return $folio;
        });

        $folio->refresh();

        return response()->json([
            'success' => true,
            'folio_id' => $folio->id,
            'added_total' => $addedTotal,
            'charges_total' => $folio->charges_total,
            'balance' => $folio->balance,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{
     *     product: Product,
     *     description: string,
     *     quantity: float,
     *     unit_price: float,
     *     tax_amount: float,
     *     total_amount: float
     * }>
     */
    private function normalizeItems(array $items, Collection $products): array
    {
        return collect($items)
            ->map(function (array $item, int $index) use ($products) {
                $productId = (int) $item['product_id'];
                /** @var \App\Models\Product|null $product */
                $product = $products->get($productId);

                if ($product === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'Produit introuvable.',
                    ]);
                }

                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $taxAmount = isset($item['tax_amount']) ? (float) $item['tax_amount'] : 0.0;
                $totalAmount = (float) $item['total_amount'];
                $baseAmount = round($quantity * $unitPrice, 2);
                $expectedTotal = round($baseAmount + $taxAmount, 2);

                if (abs($expectedTotal - $totalAmount) > 0.05) {
                    throw ValidationException::withMessages([
                        "items.{$index}.total_amount" => 'Les montants ne sont pas cohérents.',
                    ]);
                }

                return [
                    'product' => $product,
                    'description' => $item['name'] ?? $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $expectedTotal,
                ];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return Collection<int, Product>
     */
    private function productsForItems(array $items, string $tenantId, int $hotelId): Collection
    {
        $ids = collect($items)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->all();

        return Product::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    private function generateFolioCode(): string
    {
        return sprintf('POS-%s', Str::upper(Str::random(8)));
    }

    private function requireActiveHotel(Request $request): Hotel
    {
        $hotel = $this->activeHotel($request);

        abort_if($hotel === null, 403, 'Veuillez sélectionner un hôtel actif.');

        return $hotel;
    }
}
