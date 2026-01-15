<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockTransferRequest;
use App\Models\Hotel;
use App\Models\StockItem;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $this->authorize('stock.transfers.create');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->resolveHotel($user);
        $data = $request->validated();

        $transfer = StockTransfer::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotel->id,
            'from_location_id' => $data['from_location_id'],
            'to_location_id' => $data['to_location_id'],
            'status' => StockTransfer::STATUS_DRAFT,
            'created_by_user_id' => $user->id,
        ]);

        $lines = [];

        foreach ($data['lines'] as $line) {
            $unitCost = (float) ($line['unit_cost'] ?? 0);
            $total = $unitCost * (float) $line['quantity'];

            $lines[] = [
                'tenant_id' => $user->tenant_id,
                'hotel_id' => $hotel->id,
                'stock_transfer_id' => $transfer->id,
                'stock_item_id' => $line['stock_item_id'],
                'quantity' => $line['quantity'],
                'unit_cost' => $unitCost,
                'total_cost' => $total,
                'currency' => $line['currency'] ?? 'XAF',
            ];
        }

        StockTransferLine::query()->insert($lines);

        return response()->json([
            'transfer' => $transfer->load('lines.stockItem', 'fromLocation', 'toLocation'),
        ], 201);
    }

    public function create(Request $request): Response
    {
        $this->authorize('stock.transfers.create');

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

        return Inertia::render('Stock/Transfers/Create', [
            'storageLocations' => $storageLocations,
            'stockItems' => $stockItems,
        ]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('stock.transfers.create');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->resolveHotel($user);

        $transfers = StockTransfer::query()
            ->with('fromLocation', 'toLocation')
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Stock/Transfers/Index', [
            'transfers' => $transfers->map(fn (StockTransfer $transfer) => $this->transferPayload($transfer)),
            'permissions' => [
                'can_create_transfer' => $user->can('stock.transfers.create'),
                'can_complete_transfer' => $user->can('stock.transfers.complete'),
            ],
        ]);
    }

    public function show(Request $request, StockTransfer $stockTransfer): Response
    {
        $this->authorize('stock.transfers.create');
        $user = $request->user();
        $this->assertTenantHotel($user, $stockTransfer);

        $stockTransfer->load('fromLocation', 'toLocation', 'lines.stockItem');

        return Inertia::render('Stock/Transfers/Show', [
            'transfer' => $this->transferPayload($stockTransfer),
        ]);
    }

    public function complete(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('stock.transfers.complete');
        $user = $request->user();
        $this->assertTenantHotel($user, $stockTransfer);

        $this->inventoryService->completeTransfer($stockTransfer, $user);

        return response()->json([
            'transfer' => $stockTransfer->load('lines.stockItem', 'fromLocation', 'toLocation'),
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

    private function assertTenantHotel(?User $user, StockTransfer $transfer): void
    {
        if (! $user) {
            abort(403);
        }

        if ($transfer->tenant_id !== $user->tenant_id
            || (int) $transfer->hotel_id !== (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0)) {
            throw new ModelNotFoundException;
        }
    }

    private function transferPayload(StockTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'from_location' => $transfer->fromLocation?->only(['id', 'name']),
            'to_location' => $transfer->toLocation?->only(['id', 'name']),
            'status' => $transfer->status,
            'currency' => $transfer->lines->first()?->currency ?? 'XAF',
            'lines' => $transfer->lines->map(fn ($line) => [
                'id' => $line->id,
                'stock_item' => $line->stockItem?->only(['id', 'name', 'sku', 'unit']),
                'quantity' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_cost,
                'total_cost' => (float) $line->total_cost,
                'currency' => $line->currency,
            ])->values()->toArray(),
            'created_at' => $transfer->created_at?->toDateTimeString(),
        ];
    }
}
