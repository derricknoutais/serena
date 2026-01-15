<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceInterventionItem;
use App\Models\StockInventory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockOnHand;
use App\Models\StockPurchase;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $hotel = $this->resolveHotel($user);

        $purchases = StockPurchase::query()
            ->with('storageLocation', 'lines.stockItem')
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->latest()
            ->limit(8)
            ->get();

        $transfers = StockTransfer::query()
            ->with('fromLocation', 'toLocation', 'lines.stockItem')
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->latest()
            ->limit(8)
            ->get();

        $inventories = StockInventory::query()
            ->with('storageLocation', 'lines.stockItem')
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->latest()
            ->limit(8)
            ->get();

        $stockOnHand = StockOnHand::query()
            ->with('stockItem', 'storageLocation')
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->orderByDesc('quantity_on_hand')
            ->limit(20)
            ->get();
        $recentMovements = StockMovement::query()
            ->with(['lines.stockItem', 'fromLocation', 'toLocation'])
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->latest()
            ->limit(20)
            ->get();

        $availableStorageLocations = StorageLocation::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $availableStockItems = StockItem::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'default_purchase_price', 'currency']);

        $maintenanceConsumptionSummary = MaintenanceInterventionItem::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(total_cost), 0) as total_cost')
            ->first();

        $permissions = $user->can('stock.purchases.create')
            || $user->can('stock.transfers.create')
            || $user->can('stock.inventories.create');

        return Inertia::render('Stock/Index', [
            'purchases' => $purchases,
            'transfers' => $transfers,
            'inventories' => $inventories,
            'stockOnHand' => $stockOnHand,
            'storageLocations' => $availableStorageLocations,
            'stockItems' => $availableStockItems,
            'movements' => $recentMovements->map(fn (StockMovement $movement) => $this->movementPayload($movement)),
            'permissions' => [
                'can_create_purchase' => $user->can('stock.purchases.create'),
                'can_receive_purchase' => $user->can('stock.purchases.receive'),
                'can_create_transfer' => $user->can('stock.transfers.create'),
                'can_complete_transfer' => $user->can('stock.transfers.complete'),
                'can_create_inventory' => $user->can('stock.inventories.create'),
                'can_post_inventory' => $user->can('stock.inventories.post'),
            ],
            'maintenanceConsumptionSummary' => [
                'period_label' => '30 derniers jours',
                'total_quantity' => (float) ($maintenanceConsumptionSummary->total_quantity ?? 0),
                'total_cost' => (float) ($maintenanceConsumptionSummary->total_cost ?? 0),
            ],
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

    private function movementPayload(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'movement_type' => $movement->movement_type,
            'occurred_at' => $movement->occurred_at?->toDateTimeString(),
            'from_location' => $movement->fromLocation?->only(['id', 'name']),
            'to_location' => $movement->toLocation?->only(['id', 'name']),
            'reference' => $this->movementReference($movement),
            'lines' => $movement->lines->map(function ($line) {
                return [
                    'id' => $line->id,
                    'stock_item' => $line->stockItem?->only(['id', 'name', 'sku', 'unit']),
                    'quantity' => (float) $line->quantity,
                    'unit_cost' => (float) $line->unit_cost,
                    'total_cost' => (float) $line->total_cost,
                    'currency' => $line->currency,
                ];
            })->values()->toArray(),
            'movement_url' => route('stock.movements.show', ['stockMovement' => $movement->id]),
        ];
    }

    private function movementReference(StockMovement $movement): ?array
    {
        if (! $movement->reference_type || ! $movement->reference_id) {
            return null;
        }

        return match ($movement->reference_type) {
            MaintenanceIntervention::class => [
                'type' => 'maintenance_intervention',
                'label' => sprintf('Intervention #%s', $movement->reference_id),
                'url' => route('maintenance-interventions.show', ['maintenanceIntervention' => $movement->reference_id]),
            ],
            StockPurchase::class => [
                'type' => 'stock_purchase',
                'label' => sprintf('Réception #%s', $movement->reference_id),
                'url' => null,
            ],
            StockTransfer::class => [
                'type' => 'stock_transfer',
                'label' => sprintf('Transfert #%s', $movement->reference_id),
                'url' => null,
            ],
            StockInventory::class => [
                'type' => 'stock_inventory',
                'label' => sprintf('Inventaire #%s', $movement->reference_id),
                'url' => null,
            ],
            default => null,
        };
    }
}
