<?php

namespace App\Services;

use App\Exceptions\InventoryException;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceInterventionCost;
use App\Models\MaintenanceInterventionItem;
use App\Models\StockInventory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockOnHand;
use App\Models\StockPurchase;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private KitExpander $kitExpander,
        private MaintenanceCostService $maintenanceCostService,
    ) {}

    public function consumeForIntervention(
        MaintenanceIntervention $intervention,
        StockItem $item,
        StorageLocation $location,
        float $quantity,
        ?float $unitCost = null,
        ?User $actor = null,
        bool $allowNegative = false,
    ): Collection {
        $this->ensureScopeConsistency($intervention, $item, $location);

        $overrideUnitCost = $unitCost ?? null;
        $expandedEntries = $this->kitExpander->expand($item, $quantity, $overrideUnitCost);

        /** @var Collection<int, MaintenanceInterventionItem> $entries */
        return DB::transaction(function () use (
            $intervention,
            $location,
            $expandedEntries,
            $actor,
            $allowNegative,
            $item,
        ) {
            $movement = StockMovement::create([
                'tenant_id' => $intervention->tenant_id,
                'hotel_id' => $intervention->hotel_id,
                'movement_type' => StockMovement::TYPE_CONSUME,
                'occurred_at' => now(),
                'from_location_id' => $location->id,
                'reference_type' => MaintenanceIntervention::class,
                'reference_id' => $intervention->id,
                'created_by_user_id' => $actor?->id,
            ]);

            $entries = collect();

            foreach ($expandedEntries as $entry) {
                /** @var StockItem $component */
                $component = $entry['stock_item'];
                $componentQuantity = (float) $entry['quantity'];
                $componentUnitCost = (float) $entry['unit_cost'];
                $componentTotal = $componentQuantity * $componentUnitCost;

                $movement->lines()->create([
                    'tenant_id' => $movement->tenant_id,
                    'hotel_id' => $movement->hotel_id,
                    'stock_item_id' => $component->id,
                    'quantity' => $componentQuantity,
                    'unit_cost' => $componentUnitCost,
                    'total_cost' => $componentTotal,
                    'currency' => $component->currency,
                ]);

                $this->adjustStockOnHand(
                    $intervention->tenant_id,
                    $intervention->hotel_id,
                    $location->id,
                    $component->id,
                    -$componentQuantity,
                    $allowNegative,
                );

                $notes = $item->isKit() ? sprintf('Kit %s', $item->name) : null;

                $itemEntry = $intervention->items()->create([
                    'tenant_id' => $intervention->tenant_id,
                    'hotel_id' => $intervention->hotel_id,
                    'stock_item_id' => $component->id,
                    'storage_location_id' => $location->id,
                    'quantity' => $componentQuantity,
                    'unit_cost' => $componentUnitCost,
                    'total_cost' => $componentTotal,
                    'notes' => $notes,
                    'created_by_user_id' => $actor?->id,
                ]);

                $intervention->costs()->create([
                    'tenant_id' => $intervention->tenant_id,
                    'hotel_id' => $intervention->hotel_id,
                    'maintenance_intervention_id' => $intervention->id,
                    'cost_type' => MaintenanceInterventionCost::TYPE_PARTS,
                    'label' => sprintf('Pièce: %s', $component->name),
                    'quantity' => $componentQuantity,
                    'unit_price' => $componentUnitCost,
                    'currency' => $component->currency,
                    'source' => MaintenanceInterventionCost::SOURCE_STOCK,
                    'created_by_user_id' => $actor?->id,
                ]);

                $entries->push($itemEntry);
            }

            $this->maintenanceCostService->recomputeInterventionTotals($intervention);

            return $entries;
        });
    }

    public function receivePurchase(StockPurchase $purchase, User $actor): void
    {
        if ($purchase->status !== StockPurchase::STATUS_DRAFT) {
            throw new InventoryException('Achat déjà reçu ou annulé.');
        }

        DB::transaction(function () use ($purchase, $actor): void {
            $movement = StockMovement::create([
                'tenant_id' => $purchase->tenant_id,
                'hotel_id' => $purchase->hotel_id,
                'movement_type' => StockMovement::TYPE_PURCHASE,
                'occurred_at' => now(),
                'to_location_id' => $purchase->storage_location_id,
                'reference_type' => StockPurchase::class,
                'reference_id' => $purchase->id,
                'created_by_user_id' => $actor?->id,
            ]);

            foreach ($purchase->lines as $line) {
                $stockItem = $line->stockItem ?? StockItem::query()->find($line->stock_item_id);

                if (! $stockItem) {
                    continue;
                }

                $expanded = $this->kitExpander->expand(
                    $stockItem,
                    (float) $line->quantity,
                    (float) ($line->unit_cost ?? 0),
                );

                foreach ($expanded as $entry) {
                    $component = $entry['stock_item'];
                    $quantity = (float) $entry['quantity'];
                    $unitCost = (float) $entry['unit_cost'];

                    $movement->lines()->create([
                        'tenant_id' => $movement->tenant_id,
                        'hotel_id' => $movement->hotel_id,
                        'stock_item_id' => $component->id,
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'total_cost' => $unitCost * $quantity,
                        'currency' => $component->currency,
                    ]);

                    $this->adjustStockOnHand(
                        $purchase->tenant_id,
                        $purchase->hotel_id,
                        $purchase->storage_location_id,
                        $component->id,
                        $quantity,
                    );
                }
            }

            $purchase->forceFill([
                'status' => StockPurchase::STATUS_RECEIVED,
                'received_at' => now(),
                'received_by_user_id' => $actor?->id,
            ])->save();
        });
    }

    public function completeTransfer(StockTransfer $transfer, User $actor): void
    {
        if ($transfer->status !== StockTransfer::STATUS_DRAFT) {
            throw new InventoryException('Transfert déjà finalisé.');
        }

        if ($transfer->from_location_id === $transfer->to_location_id) {
            throw new InventoryException('L’emplacement source et destination doivent être différents.');
        }

        DB::transaction(function () use ($transfer, $actor): void {
            $movement = StockMovement::create([
                'tenant_id' => $transfer->tenant_id,
                'hotel_id' => $transfer->hotel_id,
                'movement_type' => StockMovement::TYPE_TRANSFER,
                'occurred_at' => now(),
                'from_location_id' => $transfer->from_location_id,
                'to_location_id' => $transfer->to_location_id,
                'reference_type' => StockTransfer::class,
                'reference_id' => $transfer->id,
                'created_by_user_id' => $actor?->id,
            ]);

            foreach ($transfer->lines as $line) {
                $stockItem = $line->stockItem ?? StockItem::query()->find($line->stock_item_id);

                if (! $stockItem) {
                    continue;
                }

                $expanded = $this->kitExpander->expand(
                    $stockItem,
                    (float) $line->quantity,
                    (float) ($line->unit_cost ?? 0),
                );

                foreach ($expanded as $entry) {
                    $component = $entry['stock_item'];
                    $quantity = (float) $entry['quantity'];
                    $unitCost = (float) $entry['unit_cost'];

                    $movement->lines()->create([
                        'tenant_id' => $movement->tenant_id,
                        'hotel_id' => $movement->hotel_id,
                        'stock_item_id' => $component->id,
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'total_cost' => $unitCost * $quantity,
                        'currency' => $component->currency,
                    ]);

                    $this->adjustStockOnHand(
                        $transfer->tenant_id,
                        $transfer->hotel_id,
                        $transfer->from_location_id,
                        $component->id,
                        -$quantity,
                    );

                    $this->adjustStockOnHand(
                        $transfer->tenant_id,
                        $transfer->hotel_id,
                        $transfer->to_location_id,
                        $component->id,
                        $quantity,
                    );
                }
            }

            $transfer->forceFill([
                'status' => StockTransfer::STATUS_COMPLETED,
                'transferred_at' => now(),
            ])->save();
        });
    }

    public function postInventory(StockInventory $inventory, User $actor): void
    {
        if ($inventory->status !== StockInventory::STATUS_DRAFT) {
            throw new InventoryException('Inventaire déjà posté ou annulé.');
        }

        DB::transaction(function () use ($inventory, $actor): void {
            foreach ($inventory->lines as $line) {
                if ($line->variance_quantity === 0) {
                    continue;
                }

                $movement = StockMovement::create([
                    'tenant_id' => $inventory->tenant_id,
                    'hotel_id' => $inventory->hotel_id,
                    'movement_type' => StockMovement::TYPE_ADJUSTMENT,
                    'occurred_at' => now(),
                    'to_location_id' => $inventory->storage_location_id,
                    'reference_type' => StockInventory::class,
                    'reference_id' => $inventory->id,
                    'created_by_user_id' => $actor?->id,
                ]);

                $movement->lines()->create([
                    'tenant_id' => $movement->tenant_id,
                    'hotel_id' => $movement->hotel_id,
                    'stock_item_id' => $line->stock_item_id,
                    'quantity' => abs($line->variance_quantity),
                    'unit_cost' => 0,
                    'total_cost' => 0,
                    'currency' => 'XAF',
                ]);

                $this->adjustStockOnHand(
                    $inventory->tenant_id,
                    $inventory->hotel_id,
                    $inventory->storage_location_id,
                    $line->stock_item_id,
                    $line->variance_quantity,
                    true,
                );
            }

            $inventory->forceFill([
                'status' => StockInventory::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by_user_id' => $actor?->id,
            ])->save();
        });
    }

    protected function adjustStockOnHand(
        string $tenantId,
        int $hotelId,
        int $locationId,
        int $stockItemId,
        float $delta,
        bool $allowNegative = false,
    ): StockOnHand {
        $onHand = StockOnHand::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('storage_location_id', $locationId)
            ->where('stock_item_id', $stockItemId)
            ->lockForUpdate()
            ->first();

        if (! $onHand) {
            $onHand = StockOnHand::create([
                'tenant_id' => $tenantId,
                'hotel_id' => $hotelId,
                'storage_location_id' => $locationId,
                'stock_item_id' => $stockItemId,
                'quantity_on_hand' => 0,
            ]);
        }

        $newQuantity = $onHand->quantity_on_hand + $delta;

        if (! $allowNegative && $newQuantity < 0) {
            throw new InventoryException('Stock insuffisant pour cette action.');
        }

        $onHand->quantity_on_hand = $newQuantity;
        $onHand->save();

        return $onHand;
    }

    protected function ensureScopeConsistency(
        MaintenanceIntervention $intervention,
        StockItem $item,
        StorageLocation $location,
    ): void {
        if (
            $intervention->tenant_id !== $item->tenant_id
            || $intervention->tenant_id !== $location->tenant_id
            || $intervention->hotel_id !== $item->hotel_id
            || $intervention->hotel_id !== $location->hotel_id
        ) {
            throw new InventoryException('Les éléments d’inventaire doivent appartenir au même hôtel.');
        }
    }
}
