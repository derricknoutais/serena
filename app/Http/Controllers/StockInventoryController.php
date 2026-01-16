<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockInventoryRequest;
use App\Models\Hotel;
use App\Models\StockInventory;
use App\Models\StockInventoryLine;
use App\Models\StockItem;
use App\Models\StockOnHand;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\KitExpander;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockInventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
        private KitExpander $kitExpander,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('stock.inventories.create');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->resolveHotel($user);

        $inventories = StockInventory::query()
            ->with('storageLocation', 'lines')
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Stock/Inventories/Index', [
            'inventories' => $inventories->map(fn (StockInventory $inventory) => $this->inventoryPayload($inventory)),
            'permissions' => [
                'can_create_inventory' => $user->can('stock.inventories.create'),
                'can_post_inventory' => $user->can('stock.inventories.post'),
            ],
        ]);
    }

    public function store(StoreStockInventoryRequest $request): JsonResponse
    {
        $this->authorize('stock.inventories.create');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->resolveHotel($user);
        $data = $request->validated();

        $inventory = StockInventory::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotel->id,
            'storage_location_id' => $data['storage_location_id'],
            'status' => StockInventory::STATUS_DRAFT,
            'counted_at' => $data['counted_at'] ?? now(),
            'created_by_user_id' => $user->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $lines = [];
        $stockItems = StockItem::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->whereIntegerInRaw('id', collect($data['lines'])->pluck('stock_item_id'))
            ->get()
            ->keyBy('id');

        foreach ($data['lines'] as $line) {
            $stockItem = $stockItems[$line['stock_item_id']] ?? null;

            if (! $stockItem) {
                continue;
            }

            $expanded = $this->kitExpander->expand($stockItem, (float) $line['counted_quantity']);

            foreach ($expanded as $entry) {
                $component = $entry['stock_item'];
                $countedQuantity = (float) $entry['quantity'];
                $systemQuantity = StockOnHand::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('hotel_id', $hotel->id)
                    ->where('storage_location_id', $data['storage_location_id'])
                    ->where('stock_item_id', $component->id)
                    ->value('quantity_on_hand') ?? 0;

                $lines[] = [
                    'tenant_id' => $user->tenant_id,
                    'hotel_id' => $hotel->id,
                    'stock_inventory_id' => $inventory->id,
                    'stock_item_id' => $component->id,
                    'counted_quantity' => $countedQuantity,
                    'system_quantity' => $systemQuantity,
                    'variance_quantity' => $countedQuantity - (float) $systemQuantity,
                ];
            }
        }

        StockInventoryLine::query()->insert($lines);

        return response()->json([
            'inventory' => $inventory->load('lines.stockItem', 'storageLocation'),
        ], 201);
    }

    public function post(Request $request, StockInventory $stockInventory): JsonResponse
    {
        $this->authorize('stock.inventories.post');
        $user = $request->user();
        $this->assertTenantHotel($user, $stockInventory);

        $this->inventoryService->postInventory($stockInventory, $user);

        return response()->json([
            'inventory' => $stockInventory->load('lines.stockItem', 'storageLocation'),
        ]);
    }

    public function show(Request $request, StockInventory $stockInventory): Response
    {
        $this->authorize('stock.inventories.create');
        $user = $request->user();
        $this->assertTenantHotel($user, $stockInventory);

        $stockInventory->load('storageLocation', 'lines.stockItem');

        return Inertia::render('Stock/Inventories/Show', [
            'inventory' => $this->inventoryPayload($stockInventory),
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

    private function assertTenantHotel(?User $user, StockInventory $inventory): void
    {
        if (! $user) {
            abort(403);
        }

        if ($inventory->tenant_id !== $user->tenant_id
            || (int) $inventory->hotel_id !== (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0)) {
            throw new ModelNotFoundException;
        }
    }

    private function inventoryPayload(StockInventory $inventory): array
    {
        return [
            'id' => $inventory->id,
            'storage_location' => $inventory->storageLocation?->only(['id', 'name']),
            'status' => $inventory->status,
            'lines' => $inventory->lines->map(fn ($line) => [
                'id' => $line->id,
                'stock_item' => $line->stockItem?->only(['id', 'name', 'sku', 'unit']),
                'counted_quantity' => (float) $line->counted_quantity,
                'system_quantity' => (float) $line->system_quantity,
                'variance_quantity' => (float) $line->variance_quantity,
            ])->values()->toArray(),
            'created_at' => $inventory->created_at?->toDateTimeString(),
        ];
    }
}
