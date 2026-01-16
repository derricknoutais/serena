<?php

namespace App\Services;

use App\Exceptions\InventoryException;
use App\Models\StockItem;
use Illuminate\Support\Collection;

class KitExpander
{
    /**
     * Expand the given stock item and quantity into simple component entries.
     *
     * @return Collection<array{stock_item: StockItem, quantity: float, unit_cost: float}>
     */
    public function expand(StockItem $item, float $quantity, ?float $overrideUnitCost = null): Collection
    {
        if ($quantity <= 0) {
            throw new InventoryException('La quantité doit être supérieure à zéro.');
        }

        if (! $item->isKit()) {
            $unitCost = $overrideUnitCost ?? (float) ($item->default_purchase_price ?? 0);

            return collect([
                [
                    'stock_item' => $item,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ],
            ]);
        }

        $components = $item
            ->components()
            ->with('component')
            ->get()
            ->filter(fn ($component) => $component->component !== null);

        if ($components->isEmpty()) {
            throw new InventoryException('Le kit doit contenir au moins un article.');
        }

        $expanded = collect();

        foreach ($components as $component) {
            /** @var \App\Models\StockItemComponent $component */
            $componentItem = $component->component;

            if ($componentItem->isKit()) {
                throw new InventoryException('Les kits ne peuvent pas contenir d’autres kits.');
            }

            $expanded->push([
                'stock_item' => $componentItem,
                'quantity' => (float) $component->quantity * $quantity,
                'unit_cost' => (float) ($componentItem->default_purchase_price ?? 0),
            ]);
        }

        return $expanded;
    }
}
