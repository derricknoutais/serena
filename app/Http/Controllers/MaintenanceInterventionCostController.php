<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceInterventionCostRequest;
use App\Http\Requests\UpdateMaintenanceInterventionCostRequest;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceInterventionCost;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MaintenanceInterventionCostController extends Controller
{
    public function store(
        StoreMaintenanceInterventionCostRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
    ): JsonResponse {
        $this->authorize('maintenance.interventions.costs.manage');
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);
        $this->ensureMutable($maintenanceIntervention);

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

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

        $maintenanceIntervention->recalcTotalsFromCosts();

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($user)
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'cost_id' => $cost->id,
                'cost_type' => $cost->cost_type,
                'total_amount' => (float) $cost->total_amount,
            ])
            ->event('maintenance.intervention_cost_added')
            ->log('maintenance.intervention_cost_added');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh()),
        ]);
    }

    public function update(
        UpdateMaintenanceInterventionCostRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
        MaintenanceInterventionCost $maintenanceInterventionCost,
    ): JsonResponse {
        $this->authorize('maintenance.interventions.costs.manage');
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);
        $this->ensureMutable($maintenanceIntervention);
        $this->assertCostBelongsToIntervention($maintenanceIntervention, $maintenanceInterventionCost);

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $maintenanceInterventionCost->fill([
            'cost_type' => $data['cost_type'] ?? $maintenanceInterventionCost->cost_type,
            'label' => $data['label'] ?? $maintenanceInterventionCost->label,
            'quantity' => $data['quantity'] ?? $maintenanceInterventionCost->quantity,
            'unit_price' => $data['unit_price'] ?? $maintenanceInterventionCost->unit_price,
            'currency' => $data['currency'] ?? $maintenanceInterventionCost->currency,
            'notes' => $data['notes'] ?? $maintenanceInterventionCost->notes,
        ]);
        $maintenanceInterventionCost->save();

        $maintenanceIntervention->recalcTotalsFromCosts();

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($user)
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'cost_id' => $maintenanceInterventionCost->id,
                'cost_type' => $maintenanceInterventionCost->cost_type,
                'total_amount' => (float) $maintenanceInterventionCost->total_amount,
            ])
            ->event('maintenance.intervention_cost_updated')
            ->log('maintenance.intervention_cost_updated');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh()),
        ]);
    }

    public function destroy(
        MaintenanceIntervention $maintenanceIntervention,
        MaintenanceInterventionCost $maintenanceInterventionCost,
    ): JsonResponse {
        $this->authorize('maintenance.interventions.costs.manage');
        $this->assertTenantHotel(request()->user(), $maintenanceIntervention);
        $this->ensureMutable($maintenanceIntervention);
        $this->assertCostBelongsToIntervention($maintenanceIntervention, $maintenanceInterventionCost);

        $maintenanceInterventionCost->delete();
        $maintenanceIntervention->recalcTotalsFromCosts();

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy(request()->user())
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'cost_id' => $maintenanceInterventionCost->id,
            ])
            ->event('maintenance.intervention_cost_deleted')
            ->log('maintenance.intervention_cost_deleted');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh()),
        ]);
    }

    private function interventionPayload(MaintenanceIntervention $intervention): array
    {
        $intervention->loadMissing([
            'technician:id,name,company_name',
            'createdBy:id,name',
            'tickets.room:id,number',
            'tickets.type:id,name',
            'costs',
        ]);

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
            'currency' => $intervention->currency,
            'accounting_status' => $intervention->accounting_status,
            'submitted_to_accounting_at' => $intervention->submitted_to_accounting_at?->toDateTimeString(),
            'submitted_at' => $intervention->submitted_at?->toDateTimeString(),
            'approved_at' => $intervention->approved_at?->toDateTimeString(),
            'rejected_at' => $intervention->rejected_at?->toDateTimeString(),
            'rejection_reason' => $intervention->rejection_reason,
            'paid_at' => $intervention->paid_at?->toDateTimeString(),
            'costs' => $intervention->costs->map(function (MaintenanceInterventionCost $cost): array {
                return [
                    'id' => $cost->id,
                    'cost_type' => $cost->cost_type,
                    'label' => $cost->label,
                    'quantity' => (float) $cost->quantity,
                    'unit_price' => (float) $cost->unit_price,
                    'total_amount' => (float) $cost->total_amount,
                    'currency' => $cost->currency,
                    'notes' => $cost->notes,
                ];
            })->values(),
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
