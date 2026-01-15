<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockPurchaseRequest;
use App\Models\Hotel;
use App\Models\StockItem;
use App\Models\StockPurchase;
use App\Models\StockPurchaseLine;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockPurchaseController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request): Response
    {
        $this->authorize('stock.purchases.create');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->resolveHotel($user);

        $purchases = StockPurchase::query()
            ->with('storageLocation')
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Stock/Purchases/Index', [
            'purchases' => $purchases->map(fn (StockPurchase $purchase) => $this->purchasePayload($purchase)),
            'permissions' => [
                'can_create_purchase' => $user->can('stock.purchases.create'),
                'can_receive_purchase' => $user->can('stock.purchases.receive'),
            ],
        ]);
    }

    public function store(StoreStockPurchaseRequest $request): JsonResponse
    {
        $this->authorize('stock.purchases.create');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->resolveHotel($user);
        $data = $request->validated();

        $currency = $data['currency'] ?? $hotel->currency ?? 'XAF';

        $purchase = StockPurchase::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotel->id,
            'storage_location_id' => $data['storage_location_id'],
            'reference_no' => $data['reference_no'] ?? null,
            'supplier_name' => $data['supplier_name'] ?? null,
            'purchased_at' => $data['purchased_at'] ?? null,
            'currency' => $currency,
            'status' => StockPurchase::STATUS_DRAFT,
            'created_by_user_id' => $user->id,
        ]);

        $lines = [];
        $subtotal = 0;

        foreach ($data['lines'] as $line) {
            $lineCurrency = $line['currency'] ?? $currency;
            $total = (float) $line['quantity'] * (float) $line['unit_cost'];
            $subtotal += $total;

            $lines[] = [
                'tenant_id' => $user->tenant_id,
                'hotel_id' => $hotel->id,
                'stock_purchase_id' => $purchase->id,
                'stock_item_id' => $line['stock_item_id'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'total_cost' => $total,
                'currency' => $lineCurrency,
                'notes' => $line['notes'] ?? null,
            ];
        }

        StockPurchaseLine::query()->insert($lines);

        $purchase->forceFill([
            'subtotal_amount' => $subtotal,
            'total_amount' => $subtotal,
        ])->save();

        return response()->json([
            'purchase' => $purchase->load('lines.stockItem', 'storageLocation'),
        ], 201);
    }

    public function create(Request $request): Response
    {
        $this->authorize('stock.purchases.create');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->resolveHotel($user);

        $storageLocations = StorageLocation::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $stockItems = StockItem::query()
            ->select(['id', 'name', 'sku', 'unit', 'default_purchase_price', 'currency'])
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Stock/Purchases/Create', [
            'storageLocations' => $storageLocations,
            'stockItems' => $stockItems,
        ]);
    }

    public function show(Request $request, StockPurchase $stockPurchase): Response
    {
        $this->authorize('stock.purchases.create');
        $user = $request->user();
        $this->assertTenantHotel($user, $stockPurchase);

        $stockPurchase->load('storageLocation', 'lines.stockItem');

        return Inertia::render('Stock/Purchases/Show', [
            'purchase' => $this->purchasePayload($stockPurchase),
        ]);
    }

    public function receive(Request $request, StockPurchase $stockPurchase): JsonResponse
    {
        $this->authorize('stock.purchases.receive');
        $user = $request->user();
        $this->assertTenantHotel($user, $stockPurchase);

        $this->inventoryService->receivePurchase($stockPurchase, $user);

        return response()->json([
            'purchase' => $stockPurchase->load('lines.stockItem', 'storageLocation'),
        ]);
    }

    private function resolveHotel(?User $user): Hotel
    {
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif.');
        }

        return Hotel::query()
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail($hotelId);
    }

    private function assertTenantHotel(?User $user, StockPurchase $purchase): void
    {
        if (! $user) {
            abort(403);
        }

        if ($purchase->tenant_id !== $user->tenant_id
            || (int) $purchase->hotel_id !== (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0)) {
            throw new ModelNotFoundException;
        }
    }

    private function purchasePayload(StockPurchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'reference_no' => $purchase->reference_no,
            'supplier_name' => $purchase->supplier_name,
            'storage_location' => $purchase->storageLocation?->only(['id', 'name']),
            'status' => $purchase->status,
            'total_amount' => (float) $purchase->total_amount,
            'currency' => $purchase->currency,
            'purchased_at' => $purchase->purchased_at?->toDateString(),
            'lines' => $purchase->lines->map(fn ($line) => [
                'id' => $line->id,
                'stock_item' => $line->stockItem?->only(['id', 'name', 'sku', 'unit']),
                'quantity' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_cost,
                'total_cost' => (float) $line->total_cost,
                'notes' => $line->notes,
                'currency' => $line->currency,
            ])->values()->toArray(),
            'created_at' => $purchase->created_at?->toDateTimeString(),
        ];
    }
}
