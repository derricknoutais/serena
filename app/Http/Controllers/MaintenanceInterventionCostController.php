<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceInterventionCostRequest;
use App\Http\Requests\UpdateMaintenanceInterventionCostRequest;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceInterventionCost;
use App\Models\User;
use App\Services\MaintenanceCostService;
use Illuminate\Http\JsonResponse;

class MaintenanceInterventionCostController extends Controller
{
    public function __construct(private MaintenanceCostService $maintenanceCostService) {}

    public function store(
        StoreMaintenanceInterventionCostRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
    ): JsonResponse {
        $this->authorizeCostEdit($request->user());
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);
        $this->ensureMutable($maintenanceIntervention);

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $beforeTotal = (float) ($maintenanceIntervention->estimated_total_amount ?? $maintenanceIntervention->total_cost);

        $cost = $maintenanceIntervention->costs()->create([
            'tenant_id' => $maintenanceIntervention->tenant_id,
            'hotel_id' => $maintenanceIntervention->hotel_id,
            'cost_type' => $data['cost_type'],
            'label' => $data['label'],
            'quantity' => $data['quantity'] ?? 1,
            'unit_price' => $data['unit_price'] ?? 0,
            'currency' => $data['currency'] ?? $maintenanceIntervention->currency ?? 'XAF',
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $user->id,
        ]);

        $this->maintenanceCostService->recomputeInterventionTotals($maintenanceIntervention);
        $maintenanceIntervention->refresh();
        $afterTotal = (float) ($maintenanceIntervention->estimated_total_amount ?? $maintenanceIntervention->total_cost);

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($user)
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'cost_id' => $cost->id,
                'cost_type' => $cost->cost_type,
                'total_amount' => (float) $cost->total_amount,
                'totals_before' => $beforeTotal,
                'totals_after' => $afterTotal,
            ])
            ->event('maintenance.cost_line_added')
            ->log('maintenance.cost_line_added');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh(), $user),
        ]);
    }

    public function update(
        UpdateMaintenanceInterventionCostRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
        MaintenanceInterventionCost $maintenanceInterventionCost,
    ): JsonResponse {
        $this->authorizeCostEdit($request->user());
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);
        $this->ensureMutable($maintenanceIntervention);
        $this->assertCostBelongsToIntervention($maintenanceIntervention, $maintenanceInterventionCost);

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $beforeTotal = (float) ($maintenanceIntervention->estimated_total_amount ?? $maintenanceIntervention->total_cost);

        $maintenanceInterventionCost->fill([
            'cost_type' => $data['cost_type'] ?? $maintenanceInterventionCost->cost_type,
            'label' => $data['label'] ?? $maintenanceInterventionCost->label,
            'quantity' => $data['quantity'] ?? $maintenanceInterventionCost->quantity,
            'unit_price' => $data['unit_price'] ?? $maintenanceInterventionCost->unit_price,
            'currency' => $data['currency'] ?? $maintenanceInterventionCost->currency,
            'notes' => $data['notes'] ?? $maintenanceInterventionCost->notes,
        ]);
        $maintenanceInterventionCost->save();

        $this->maintenanceCostService->recomputeInterventionTotals($maintenanceIntervention);
        $maintenanceIntervention->refresh();
        $afterTotal = (float) ($maintenanceIntervention->estimated_total_amount ?? $maintenanceIntervention->total_cost);

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($user)
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'cost_id' => $maintenanceInterventionCost->id,
                'cost_type' => $maintenanceInterventionCost->cost_type,
                'total_amount' => (float) $maintenanceInterventionCost->total_amount,
                'totals_before' => $beforeTotal,
                'totals_after' => $afterTotal,
            ])
            ->event('maintenance.cost_line_updated')
            ->log('maintenance.cost_line_updated');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh(), $user),
        ]);
    }

    public function destroy(
        MaintenanceIntervention $maintenanceIntervention,
        MaintenanceInterventionCost $maintenanceInterventionCost,
    ): JsonResponse {
        $this->authorizeCostEdit(request()->user());
        $this->assertTenantHotel(request()->user(), $maintenanceIntervention);
        $this->ensureMutable($maintenanceIntervention);
        $this->assertCostBelongsToIntervention($maintenanceIntervention, $maintenanceInterventionCost);

        $beforeTotal = (float) ($maintenanceIntervention->estimated_total_amount ?? $maintenanceIntervention->total_cost);
        $maintenanceInterventionCost->delete();
        $this->maintenanceCostService->recomputeInterventionTotals($maintenanceIntervention);
        $maintenanceIntervention->refresh();
        $afterTotal = (float) ($maintenanceIntervention->estimated_total_amount ?? $maintenanceIntervention->total_cost);

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy(request()->user())
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'cost_id' => $maintenanceInterventionCost->id,
                'totals_before' => $beforeTotal,
                'totals_after' => $afterTotal,
            ])
            ->event('maintenance.cost_line_removed')
            ->log('maintenance.cost_line_removed');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh(), request()->user()),
        ]);
    }

    private function interventionPayload(MaintenanceIntervention $intervention, ?User $user = null): array
    {
        $intervention->loadMissing([
            'technician:id,name,company_name',
            'createdBy:id,name',
            'tickets.room:id,number',
            'tickets.type:id,name',
            'costs',
        ]);

        $canViewCosts = $user?->can('maintenance.costs.view')
            || $user?->can('maintenance.costs.edit')
            || $user?->can('maintenance.interventions.costs.manage')
            || $user?->hasAnyRole(['owner', 'manager']) === true;

        return [
            'id' => $intervention->id,
            'technician' => $intervention->technician?->only(['id', 'name', 'company_name']),
            'created_by' => $intervention->createdBy?->only(['id', 'name']),
            'started_at' => $intervention->started_at?->toDateTimeString(),
            'ended_at' => $intervention->ended_at?->toDateTimeString(),
            'summary' => $intervention->summary,
            'labor_cost' => (float) $intervention->labor_cost,
            'parts_cost' => (float) $intervention->parts_cost,
            'total_cost' => (float) $intervention->total_cost,
            'estimated_subtotal_amount' => (float) ($intervention->estimated_subtotal_amount ?? $intervention->total_cost),
            'estimated_total_amount' => (float) ($intervention->estimated_total_amount ?? $intervention->total_cost),
            'cost_mode' => $intervention->cost_mode ?? 'estimated',
            'currency' => $intervention->currency,
            'accounting_status' => $intervention->accounting_status,
            'submitted_to_accounting_at' => $intervention->submitted_to_accounting_at?->toDateTimeString(),
            'submitted_at' => $intervention->submitted_at?->toDateTimeString(),
            'approved_at' => $intervention->approved_at?->toDateTimeString(),
            'rejected_at' => $intervention->rejected_at?->toDateTimeString(),
            'rejection_reason' => $intervention->rejection_reason,
            'paid_at' => $intervention->paid_at?->toDateTimeString(),
            'costs' => $canViewCosts
                ? $intervention->costs->map(function (MaintenanceInterventionCost $cost): array {
                    return [
                        'id' => $cost->id,
                        'cost_type' => $cost->cost_type,
                        'label' => $cost->label,
                        'quantity' => (float) $cost->quantity,
                        'unit_price' => (float) $cost->unit_price,
                        'total_amount' => (float) $cost->total_amount,
                        'currency' => $cost->currency,
                        'source' => $cost->source,
                        'notes' => $cost->notes,
                    ];
                })->values()
                : [],
            'tickets' => $intervention->tickets->map(function ($ticket): array {
                return [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'status' => $ticket->status,
                    'room_number' => $ticket->room?->number,
                    'maintenance_type' => $ticket->type?->only(['id', 'name']),
                    'work_done' => $ticket->pivot?->work_done,
                    'labor_cost' => (float) ($ticket->pivot?->labor_cost ?? 0),
                    'parts_cost' => (float) ($ticket->pivot?->parts_cost ?? 0),
                ];
            })->values(),
        ];
    }

    private function authorizeCostEdit(?User $user): void
    {
        if (! $user) {
            abort(403);
        }

        if ($user->can('maintenance.costs.edit') || $user->can('maintenance.interventions.costs.manage')) {
            return;
        }

        abort(403);
    }

    private function assertTenantHotel(?User $user, MaintenanceIntervention $intervention): void
    {
        if (! $user) {
            abort(403);
        }

        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($intervention->tenant_id !== $user->tenant_id || (int) $intervention->hotel_id !== $hotelId) {
            abort(403);
        }
    }

    private function assertCostBelongsToIntervention(
        MaintenanceIntervention $intervention,
        MaintenanceInterventionCost $cost,
    ): void {
        if ($cost->maintenance_intervention_id !== $intervention->id) {
            abort(404);
        }
    }

    private function ensureMutable(MaintenanceIntervention $intervention): void
    {
        if (in_array($intervention->accounting_status, [
            MaintenanceIntervention::STATUS_APPROVED,
            MaintenanceIntervention::STATUS_PAID,
        ], true)) {
            abort(403, 'Cette intervention est verrouillée.');
        }
    }
}
