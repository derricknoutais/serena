<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceIntervention;
use App\Models\StockInventory;
use App\Models\StockMovement;
use App\Models\StockPurchase;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function show(Request $request, StockMovement $stockMovement): Response
    {
        $user = $request->user();
        $this->authorizeStockView($user, $stockMovement);

        $movement = $stockMovement->load([
            'lines.stockItem',
            'fromLocation',
            'toLocation',
            'createdBy',
        ]);

        return Inertia::render('Stock/MovementDetail', [
            'movement' => $this->movementPayload($movement),
        ]);
    }

    private function authorizeStockView(?User $user, StockMovement $movement): void
    {
        if (! $user) {
            abort(403);
        }

        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($movement->tenant_id !== $user->tenant_id || (int) $movement->hotel_id !== $hotelId) {
            abort(403);
        }

        if (! $this->canViewStock($user)) {
            abort(403);
        }
    }

    private function canViewStock(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $permissions = [
            'stock.purchases.create',
            'stock.purchases.receive',
            'stock.transfers.create',
            'stock.transfers.complete',
            'stock.inventories.create',
            'stock.inventories.post',
            'stock.items.manage',
            'stock.locations.manage',
            'stock.override_negative',
        ];

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    private function movementPayload(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'movement_type' => $movement->movement_type,
            'occurred_at' => $movement->occurred_at?->toDateTimeString(),
            'from_location' => $movement->fromLocation?->only(['id', 'name']),
            'to_location' => $movement->toLocation?->only(['id', 'name']),
            'reference' => $this->referencePayload($movement),
            'lines' => $movement->lines->map(function ($line): array {
                return [
                    'id' => $line->id,
                    'stock_item' => $line->stockItem?->only(['id', 'name', 'sku', 'unit']),
                    'quantity' => (float) $line->quantity,
                    'unit_cost' => (float) $line->unit_cost,
                    'total_cost' => (float) $line->total_cost,
                    'currency' => $line->currency,
                ];
            })->values(),
            'created_by' => $movement->createdBy?->only(['id', 'name']),
            'notes' => $movement->notes,
            'movement_url' => route('stock.movements.show', ['stockMovement' => $movement->id]),
        ];
    }

    private function referencePayload(StockMovement $movement): ?array
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
