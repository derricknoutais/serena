<?php

namespace App\Services;

use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceInterventionCost;

class MaintenanceCostService
{
    public function recomputeInterventionTotals(MaintenanceIntervention $intervention): void
    {
        $intervention->loadMissing(['costs', 'items']);

        $costs = $intervention->costs;
        $items = $intervention->items;

        $laborTotal = (float) $costs
            ->where('cost_type', MaintenanceInterventionCost::TYPE_LABOR)
            ->sum('total_amount');

        $partsTotal = (float) $costs
            ->where('cost_type', MaintenanceInterventionCost::TYPE_PARTS)
            ->sum('total_amount');

        $costTotal = (float) $costs->sum('total_amount');
        $itemsTotal = (float) $items->sum('total_cost');

        $hasStockCostLines = $costs
            ->where('cost_type', MaintenanceInterventionCost::TYPE_PARTS)
            ->where('source', MaintenanceInterventionCost::SOURCE_STOCK)
            ->isNotEmpty();

        if (! $hasStockCostLines && $itemsTotal > 0) {
            $partsTotal += $itemsTotal;
            $costTotal += $itemsTotal;
        }

        $currency = $intervention->currency ?? ($costs->first()?->currency ?? 'XAF');

        $intervention->forceFill([
            'labor_cost' => $laborTotal,
            'parts_cost' => $partsTotal,
            'total_cost' => $costTotal,
            'estimated_subtotal_amount' => $costTotal,
            'estimated_total_amount' => $costTotal,
            'currency' => $currency,
            'cost_mode' => $intervention->cost_mode ?? 'estimated',
        ])->saveQuietly();
    }
}
