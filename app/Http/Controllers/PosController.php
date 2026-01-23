<?php

namespace App\Http\Controllers;

use App\Exceptions\InventoryException;
use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Requests\StoreCounterSaleRequest;
use App\Http\Requests\StoreRoomSaleRequest;
use App\Models\BarOrder;
use App\Models\Folio;
use App\Models\Hotel;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Reservation;
use App\Models\StockItem;
use App\Models\StockOnHand;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\FolioBillingService;
use App\Services\InventoryService;
use App\Services\VapidEventNotifier;
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

    public function __construct(
        private readonly FolioBillingService $folioBilling,
        private readonly InventoryService $inventoryService,
        private readonly VapidEventNotifier $vapidEventNotifier,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('pos.view');

        $hotel = $this->requireActiveHotel($request);
        $tenantId = $request->user()->tenant_id;

        $categories = ProductCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $category) => [
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
            ->map(fn (Product $product) => [
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
            ->map(fn (PaymentMethod $method) => [
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
            ->map(fn (Reservation $reservation) => [
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
        $this->authorize('pos.create');

        $hotel = $this->requireActiveHotel($request);
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $data = $request->validated();

        $products = $this->productsForItems($data['items'], $tenantId, $hotel->id);
        $lines = $this->normalizeItems($data['items'], $products);
        $totalAmount = collect($lines)->sum(fn (array $line) => $line['total_amount']);

        $paymentMethod = PaymentMethod::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($hotel): void {
                $query->whereNull('hotel_id')
                    ->orWhere('hotel_id', $hotel->id);
            })
            ->findOrFail($data['payment_method_id']);

        try {
            $folio = DB::transaction(function () use ($hotel, $user, $lines, $totalAmount, $paymentMethod, $data): Folio {
                $label = $data['client_label'] ?? 'Vente comptoir';
                $barOrder = $this->resolveBarOrderForSale($data['bar_order_id'] ?? null, $user, $hotel);

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

                    if (! $activeSession) {
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

                $this->consumeStockForSale($lines, $hotel, $user, $barOrder, $folio);
                $this->markBarOrderPaid($barOrder);

                return $folio;
            });
        } catch (InventoryException $exception) {
            throw ValidationException::withMessages([
                'items' => $exception->getMessage(),
            ]);
        }

        $amountLabel = number_format((float) $totalAmount, 0, ',', ' ');
        $label = $data['client_label'] ?? 'Vente comptoir';
        $this->vapidEventNotifier->notifyOwnersAndManagers(
            eventKey: 'pos.sale',
            tenantId: (string) $tenantId,
            hotelId: $hotel->id,
            title: 'Vente POS',
            body: sprintf('%s : %s %s.', $label, $amountLabel, $hotel->currency ?? 'XAF'),
            url: route('pos.index'),
            tag: 'pos-sale',
        );

        return response()->json([
            'success' => true,
            'folio_id' => $folio->id,
            'total' => $totalAmount,
            'currency' => $folio->currency,
        ], 201);
    }

    public function storeRoomSale(StoreRoomSaleRequest $request): JsonResponse
    {
        $this->authorize('pos.create');

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
        $addedTotal = collect($lines)->sum(fn (array $line) => $line['total_amount']);

        try {
            $folio = DB::transaction(function () use ($reservation, $lines, $data, $hotel, $user) {
                $folio = $this->folioBilling->ensureMainFolioForReservation($reservation);
                $barOrder = $this->resolveBarOrderForSale($data['bar_order_id'] ?? null, $user, $hotel);

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

                $this->consumeStockForSale($lines, $hotel, $user, $barOrder, $folio);
                $this->markBarOrderPaid($barOrder);

                return $folio;
            });
        } catch (InventoryException $exception) {
            throw ValidationException::withMessages([
                'items' => $exception->getMessage(),
            ]);
        }

        $folio->refresh();

        $reservation->loadMissing('room');
        $amountLabel = number_format((float) $addedTotal, 0, ',', ' ');
        $this->vapidEventNotifier->notifyOwnersAndManagers(
            eventKey: 'pos.room_sale',
            tenantId: (string) $tenantId,
            hotelId: $hotel->id,
            title: 'Vente POS',
            body: sprintf(
                'Vente chambre %s (%s) : %s %s.',
                $reservation->room?->number ?? '—',
                $reservation->code ?? '—',
                $amountLabel,
                $hotel->currency ?? 'XAF',
            ),
            url: route('reservations.folio.show', ['reservation' => $reservation->id]),
            tag: 'pos-room-sale',
        );

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
            ->with(['stockItem', 'stockLocation'])
            ->get()
            ->keyBy('id');
    }

    private function generateFolioCode(): string
    {
        return sprintf('POS-%s', Str::upper(Str::random(8)));
    }

    private function resolveBarOrderForSale(?int $barOrderId, User $user, Hotel $hotel): ?BarOrder
    {
        if (! $barOrderId) {
            return null;
        }

        $order = BarOrder::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->lockForUpdate()
            ->find($barOrderId);

        if (! $order) {
            return null;
        }

        return $order;
    }

    private function markBarOrderPaid(?BarOrder $order): void
    {
        if (! $order) {
            return;
        }

        if (! in_array($order->status, [BarOrder::STATUS_DRAFT, BarOrder::STATUS_OPEN], true)) {
            return;
        }

        $order->update([
            'status' => BarOrder::STATUS_PAID,
            'closed_at' => now(),
        ]);
    }

    /**
     * @param  list<array{
     *     product: Product,
     *     description: string,
     *     quantity: float,
     *     unit_price: float,
     *     tax_amount: float,
     *     total_amount: float
     * }>  $lines
     */
    private function consumeStockForSale(array $lines, Hotel $hotel, User $user, ?BarOrder $barOrder, Folio $folio): void
    {
        $managedLines = array_filter($lines, fn (array $line) => (bool) $line['product']->manage_stock);

        if ($managedLines === []) {
            return;
        }

        if (! $user->can('pos.stock.consume')) {
            abort(403);
        }

        if ($barOrder && $barOrder->stock_consumed_at !== null) {
            return;
        }

        $defaultLocationId = $hotel->default_bar_stock_location_id;

        $requirements = [];
        $locationIds = [];
        $stockItemIds = [];

        foreach ($managedLines as $line) {
            /** @var Product $product */
            $product = $line['product'];
            $stockItemId = $product->stock_item_id;

            if (! $stockItemId) {
                throw ValidationException::withMessages([
                    'items' => "Le produit {$product->name} n’est pas relié à un article de stock.",
                ]);
            }

            $locationId = $product->stock_location_id ?? $defaultLocationId;

            if (! $locationId) {
                throw ValidationException::withMessages([
                    'items' => 'Veuillez définir un emplacement de stock bar par défaut.',
                ]);
            }

            $quantityPerUnit = (float) ($product->stock_quantity_per_unit ?? 1);
            $quantityToConsume = (float) $line['quantity'] * $quantityPerUnit;

            if ($quantityToConsume <= 0) {
                continue;
            }

            $key = sprintf('%s:%s', $locationId, $stockItemId);
            $requirements[$key] = [
                'location_id' => $locationId,
                'stock_item_id' => $stockItemId,
                'quantity' => ($requirements[$key]['quantity'] ?? 0) + $quantityToConsume,
            ];

            $locationIds[] = $locationId;
            $stockItemIds[] = $stockItemId;
        }

        $locations = StorageLocation::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->whereIn('id', array_unique($locationIds))
            ->get()
            ->keyBy('id');

        $stockItems = StockItem::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->whereIn('id', array_unique($stockItemIds))
            ->get()
            ->keyBy('id');

        $allowNegative = $user->can('stock.override_negative');

        if (! $allowNegative) {
            foreach ($requirements as $requirement) {
                $onHand = StockOnHand::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('hotel_id', $hotel->id)
                    ->where('storage_location_id', $requirement['location_id'])
                    ->where('stock_item_id', $requirement['stock_item_id'])
                    ->value('quantity_on_hand');

                if ((float) ($onHand ?? 0) < (float) $requirement['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => 'Stock insuffisant pour finaliser la commande.',
                    ]);
                }
            }
        }

        $referenceType = $barOrder ? BarOrder::class : Folio::class;
        $referenceId = $barOrder?->id ?? $folio->id;
        $notes = $barOrder
            ? sprintf('POS table #%s', $barOrder->id)
            : sprintf('POS vente #%s', $folio->code ?? $folio->id);

        foreach ($requirements as $requirement) {
            $location = $locations->get($requirement['location_id']);
            $stockItem = $stockItems->get($requirement['stock_item_id']);

            if (! $location || ! $stockItem) {
                continue;
            }

            $this->inventoryService->consumeForPosSale(
                $user->tenant_id,
                $hotel->id,
                $stockItem,
                $location,
                (float) $requirement['quantity'],
                $referenceType,
                $referenceId,
                $user,
                $allowNegative,
                $notes,
            );
        }

        if ($barOrder) {
            $barOrder->forceFill([
                'stock_consumed_at' => now(),
                'stock_returned_at' => null,
            ])->save();
        }

        activity('pos')
            ->performedOn($folio)
            ->causedBy($user)
            ->withProperties([
                'order_id' => $barOrder?->id,
                'location_ids' => array_values(array_unique($locationIds)),
                'lines_count' => count($requirements),
            ])
            ->event('pos.stock_consumed')
            ->log('pos.stock_consumed');
    }

    private function requireActiveHotel(Request $request): Hotel
    {
        $hotel = $this->activeHotel($request);

        abort_if($hotel === null, 403, 'Veuillez sélectionner un hôtel actif.');

        return $hotel;
    }
}
