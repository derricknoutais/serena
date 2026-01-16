<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveMaintenanceInterventionRequest;
use App\Http\Requests\AttachMaintenanceInterventionTicketRequest;
use App\Http\Requests\DetachMaintenanceInterventionTicketRequest;
use App\Http\Requests\MarkPaidMaintenanceInterventionRequest;
use App\Http\Requests\RejectMaintenanceInterventionRequest;
use App\Http\Requests\StoreMaintenanceInterventionItemRequest;
use App\Http\Requests\StoreMaintenanceInterventionRequest;
use App\Http\Requests\SubmitMaintenanceInterventionRequest;
use App\Http\Requests\UpdateMaintenanceInterventionRequest;
use App\Models\Hotel;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceInterventionItem;
use App\Models\MaintenanceTicket;
use App\Models\StockInventory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockPurchase;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceInterventionController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function show(Request $request, MaintenanceIntervention $maintenanceIntervention): JsonResponse|\Inertia\Response
    {
        $this->authorize('maintenance.interventions.update');
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);

        /** @var User|null $user */
        $user = $request->user();
        $payload = $this->interventionPayload($maintenanceIntervention, $user);

        $availableTickets = MaintenanceTicket::query()
            ->where('tenant_id', $maintenanceIntervention->tenant_id)
            ->where('hotel_id', $maintenanceIntervention->hotel_id)
            ->whereIn('status', [
                MaintenanceTicket::STATUS_OPEN,
                MaintenanceTicket::STATUS_IN_PROGRESS,
            ])
            ->whereNotIn('id', $maintenanceIntervention->tickets->pluck('id')->all())
            ->with('room:id,number')
            ->orderByDesc('opened_at')
            ->get()
            ->map(function (MaintenanceTicket $ticket): array {
                return [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'room_number' => $ticket->room?->number,
                    'status' => $ticket->status,
                ];
            })
            ->values();

        $availableStorageLocations = StorageLocation::query()
            ->where('tenant_id', $maintenanceIntervention->tenant_id)
            ->where('hotel_id', $maintenanceIntervention->hotel_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $availableStockItems = StockItem::query()
            ->where('tenant_id', $maintenanceIntervention->tenant_id)
            ->where('hotel_id', $maintenanceIntervention->hotel_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit']);

        $stockMovements = StockMovement::query()
            ->where('tenant_id', $maintenanceIntervention->tenant_id)
            ->where('hotel_id', $maintenanceIntervention->hotel_id)
            ->where('reference_type', MaintenanceIntervention::class)
            ->where('reference_id', $maintenanceIntervention->id)
            ->with(['lines.stockItem', 'fromLocation', 'toLocation', 'createdBy'])
            ->orderByDesc('occurred_at')
            ->get()
            ->map(fn (StockMovement $movement): array => $this->movementPayload($movement))
            ->values();

        if ($request->wantsJson()) {
            return response()->json([
                'intervention' => $payload,
                'available_tickets' => $availableTickets,
                'available_storage_locations' => $availableStorageLocations,
                'available_stock_items' => $availableStockItems,
                'stock_movements' => $stockMovements->all(),
            ]);
        }

        return Inertia::render('Maintenance/InterventionDetail', [
            'intervention' => $payload,
            'availableTickets' => $availableTickets,
            'availableStorageLocations' => $availableStorageLocations,
            'availableStockItems' => $availableStockItems,
            'stockMovements' => $stockMovements,
            'permissions' => [
                'can_update' => $user?->can('maintenance.interventions.update') ?? false,
                'can_submit' => $user?->can('maintenance.interventions.submit') ?? false,
                'can_approve' => $user?->can('maintenance.interventions.approve') ?? false,
                'can_reject' => $user?->can('maintenance.interventions.reject') ?? false,
                'can_mark_paid' => $user?->can('maintenance.interventions.mark_paid') ?? false,
                'can_view_costs' => $this->canViewCosts($user),
                'can_edit_costs' => $this->canEditCosts($user),
                'can_add_stock_items' => $user?->can('maintenance.interventions.add_stock_items') ?? false,
                'can_override_negative_stock' => $user?->can('stock.override_negative') ?? false,
                'can_modify_submitted_stock_items' => $this->isManagerOrOwner($user),
                'can_override_unit_cost' => $this->canOverrideUnitCost($user),
            ],
        ]);
    }

    public function store(StoreMaintenanceInterventionRequest $request): JsonResponse
    {
        $this->authorize('maintenance.interventions.create');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->resolveHotel($user);
        $data = $request->validated();

        $intervention = MaintenanceIntervention::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotel->id,
            'technician_id' => $data['technician_id'] ?? null,
            'created_by_user_id' => $user->id,
            'started_at' => $data['started_at'] ?? null,
            'ended_at' => $data['ended_at'] ?? null,
            'summary' => $data['summary'] ?? null,
            'labor_cost' => $data['labor_cost'] ?? 0,
            'parts_cost' => $data['parts_cost'] ?? 0,
            'currency' => $data['currency'] ?? $hotel->currency ?? 'XAF',
            'accounting_status' => MaintenanceIntervention::STATUS_DRAFT,
        ]);

        $this->syncTickets($intervention, $data['tickets'] ?? null, $user->tenant_id, $hotel->id);
        $this->syncLegacyCosts($intervention, $data);

        activity('maintenance')
            ->performedOn($intervention)
            ->causedBy($user)
            ->withProperties([
                'intervention_id' => $intervention->id,
                'technician_id' => $intervention->technician_id,
                'labor_cost' => $intervention->labor_cost,
                'parts_cost' => $intervention->parts_cost,
                'total_cost' => $intervention->total_cost,
                'currency' => $intervention->currency,
            ])
            ->event('maintenance.intervention_created')
            ->log('maintenance.intervention_created');

        return response()->json([
            'intervention' => $this->interventionPayload($intervention->fresh(), $user),
        ]);
    }

    public function update(
        UpdateMaintenanceInterventionRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
    ): JsonResponse {
        $this->authorize('maintenance.interventions.update');
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);
        $this->ensureMutable($maintenanceIntervention);

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $maintenanceIntervention->fill([
            'technician_id' => $data['technician_id'] ?? $maintenanceIntervention->technician_id,
            'started_at' => $data['started_at'] ?? $maintenanceIntervention->started_at,
            'ended_at' => $data['ended_at'] ?? $maintenanceIntervention->ended_at,
            'summary' => $data['summary'] ?? $maintenanceIntervention->summary,
            'labor_cost' => $data['labor_cost'] ?? $maintenanceIntervention->labor_cost,
            'parts_cost' => $data['parts_cost'] ?? $maintenanceIntervention->parts_cost,
            'currency' => $data['currency'] ?? $maintenanceIntervention->currency,
        ]);

        $maintenanceIntervention->save();

        $this->syncTickets(
            $maintenanceIntervention,
            array_key_exists('tickets', $data) ? $data['tickets'] : null,
            $maintenanceIntervention->tenant_id,
            $maintenanceIntervention->hotel_id,
        );

        $this->syncLegacyCosts($maintenanceIntervention, $data);

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($user)
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'technician_id' => $maintenanceIntervention->technician_id,
                'labor_cost' => $maintenanceIntervention->labor_cost,
                'parts_cost' => $maintenanceIntervention->parts_cost,
                'total_cost' => $maintenanceIntervention->total_cost,
                'currency' => $maintenanceIntervention->currency,
            ])
            ->event('maintenance.intervention_updated')
            ->log('maintenance.intervention_updated');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh(), $request->user()),
        ]);
    }

    public function attachTicket(
        AttachMaintenanceInterventionTicketRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
    ): JsonResponse {
        $this->authorize('maintenance.interventions.update');
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);
        $this->ensureMutable($maintenanceIntervention);

        $data = $request->validated();
        $ticket = $this->findTicket($maintenanceIntervention, (int) $data['maintenance_ticket_id']);

        $payload = [
            'tenant_id' => $maintenanceIntervention->tenant_id,
            'hotel_id' => $maintenanceIntervention->hotel_id,
            'work_done' => $data['work_done'] ?? null,
            'labor_cost' => $data['labor_cost'] ?? 0,
            'parts_cost' => $data['parts_cost'] ?? 0,
        ];

        $maintenanceIntervention->tickets()->syncWithoutDetaching([
            $ticket->id => $payload,
        ]);

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($request->user())
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'maintenance_ticket_id' => $ticket->id,
            ])
            ->event('maintenance.intervention_ticket_attached')
            ->log('maintenance.intervention_ticket_attached');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh(), $request->user()),
        ]);
    }

    public function detachTicket(
        DetachMaintenanceInterventionTicketRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
    ): JsonResponse {
        $this->authorize('maintenance.interventions.update');
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);
        $this->ensureMutable($maintenanceIntervention);

        $data = $request->validated();

        $maintenanceIntervention->tickets()->detach((int) $data['maintenance_ticket_id']);

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($request->user())
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'maintenance_ticket_id' => (int) $data['maintenance_ticket_id'],
            ])
            ->event('maintenance.intervention_ticket_detached')
            ->log('maintenance.intervention_ticket_detached');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh(), $request->user()),
        ]);
    }

    public function storeItem(
        StoreMaintenanceInterventionItemRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
    ): JsonResponse {
        $this->authorize('maintenance.interventions.add_stock_items');
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);
        $this->ensureMutable($maintenanceIntervention);

        if (
            $maintenanceIntervention->accounting_status === MaintenanceIntervention::STATUS_SUBMITTED
            && ! $this->isManagerOrOwner($request->user())
        ) {
            abort(403, 'Seule une personne responsable peut modifier les pièces après la soumission.');
        }

        $data = $request->validated();

        $location = StorageLocation::query()
            ->where('tenant_id', $maintenanceIntervention->tenant_id)
            ->where('hotel_id', $maintenanceIntervention->hotel_id)
            ->findOrFail($data['storage_location_id']);

        $item = StockItem::query()
            ->where('tenant_id', $maintenanceIntervention->tenant_id)
            ->where('hotel_id', $maintenanceIntervention->hotel_id)
            ->findOrFail($data['stock_item_id']);

        $beforeTotal = (float) ($maintenanceIntervention->estimated_total_amount ?? $maintenanceIntervention->total_cost);

        $entries = $this->inventoryService->consumeForIntervention(
            $maintenanceIntervention,
            $item,
            $location,
            (float) $data['quantity'],
            $this->determineUnitCost($data, $request->user(), $item),
            $request->user(),
            $request->boolean('allow_negative_stock')
                && $request->user()?->can('stock.override_negative'),
        );
        $maintenanceIntervention->refresh();
        $afterTotal = (float) ($maintenanceIntervention->estimated_total_amount ?? $maintenanceIntervention->total_cost);

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($request->user())
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'items' => $entries->map(fn ($entry) => [
                    'stock_item_id' => $entry->stock_item_id,
                    'quantity' => (float) $entry->quantity,
                    'unit_cost' => (float) $entry->unit_cost,
                    'total_cost' => (float) $entry->total_cost,
                ])->toArray(),
                'totals_before' => $beforeTotal,
                'totals_after' => $afterTotal,
            ])
            ->event('maintenance.stock_consumed_for_intervention')
            ->log('maintenance.stock_consumed_for_intervention');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh(), $request->user()),
        ]);
    }

    public function submit(
        SubmitMaintenanceInterventionRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
    ): JsonResponse {
        $this->authorize('maintenance.interventions.submit');
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);
        $this->ensureMutable($maintenanceIntervention);
        $this->ensureWorkDoneOnSubmit($maintenanceIntervention);

        /** @var User $user */
        $user = $request->user();

        $maintenanceIntervention->forceFill([
            'accounting_status' => MaintenanceIntervention::STATUS_SUBMITTED,
            'submitted_to_accounting_at' => now(),
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->id,
            'rejected_at' => null,
            'rejected_by_user_id' => null,
            'rejection_reason' => null,
        ])->save();

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($user)
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'accounting_status' => $maintenanceIntervention->accounting_status,
                'submitted_to_accounting_at' => $maintenanceIntervention->submitted_to_accounting_at?->toDateTimeString(),
            ])
            ->event('maintenance.intervention_submitted')
            ->log('maintenance.intervention_submitted');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh(), $request->user()),
        ]);
    }

    public function approve(
        ApproveMaintenanceInterventionRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
    ): JsonResponse {
        $this->authorize('maintenance.interventions.approve');
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);

        if ($maintenanceIntervention->accounting_status !== MaintenanceIntervention::STATUS_SUBMITTED) {
            abort(422, 'Seules les interventions soumises peuvent être approuvées.');
        }

        /** @var User $user */
        $user = $request->user();

        $maintenanceIntervention->forceFill([
            'accounting_status' => MaintenanceIntervention::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $user->id,
            'rejected_at' => null,
            'rejected_by_user_id' => null,
            'rejection_reason' => null,
        ])->save();

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($user)
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'accounting_status' => $maintenanceIntervention->accounting_status,
                'approved_at' => $maintenanceIntervention->approved_at?->toDateTimeString(),
            ])
            ->event('maintenance.intervention_approved')
            ->log('maintenance.intervention_approved');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh(), $user),
        ]);
    }

    public function reject(
        RejectMaintenanceInterventionRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
    ): JsonResponse {
        $this->authorize('maintenance.interventions.reject');
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);

        if ($maintenanceIntervention->accounting_status !== MaintenanceIntervention::STATUS_SUBMITTED) {
            abort(422, 'Seules les interventions soumises peuvent être rejetées.');
        }

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $maintenanceIntervention->forceFill([
            'accounting_status' => MaintenanceIntervention::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by_user_id' => $user->id,
            'rejection_reason' => $data['rejection_reason'],
        ])->save();

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($user)
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'accounting_status' => $maintenanceIntervention->accounting_status,
                'rejection_reason' => $maintenanceIntervention->rejection_reason,
                'rejected_at' => $maintenanceIntervention->rejected_at?->toDateTimeString(),
            ])
            ->event('maintenance.intervention_rejected')
            ->log('maintenance.intervention_rejected');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh(), $user),
        ]);
    }

    public function markPaid(
        MarkPaidMaintenanceInterventionRequest $request,
        MaintenanceIntervention $maintenanceIntervention,
    ): JsonResponse {
        $this->authorize('maintenance.interventions.mark_paid');
        $this->assertTenantHotel($request->user(), $maintenanceIntervention);

        if ($maintenanceIntervention->accounting_status !== MaintenanceIntervention::STATUS_APPROVED) {
            abort(422, 'Seules les interventions approuvées peuvent être marquées payées.');
        }

        /** @var User $user */
        $user = $request->user();

        $maintenanceIntervention->forceFill([
            'accounting_status' => MaintenanceIntervention::STATUS_PAID,
            'paid_at' => now(),
            'paid_by_user_id' => $user->id,
        ])->save();

        activity('maintenance')
            ->performedOn($maintenanceIntervention)
            ->causedBy($user)
            ->withProperties([
                'intervention_id' => $maintenanceIntervention->id,
                'accounting_status' => $maintenanceIntervention->accounting_status,
                'paid_at' => $maintenanceIntervention->paid_at?->toDateTimeString(),
            ])
            ->event('maintenance.intervention_paid')
            ->log('maintenance.intervention_paid');

        return response()->json([
            'intervention' => $this->interventionPayload($maintenanceIntervention->fresh(), $user),
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
            'items.stockItem:id,name,sku,unit,default_purchase_price',
            'items.storageLocation:id,name',
        ]);

        $canViewCosts = $this->canViewCosts($user);

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
            'stock_location' => $intervention->stockLocation?->only(['id', 'name']),
            'accounting_status' => $intervention->accounting_status,
            'submitted_to_accounting_at' => $intervention->submitted_to_accounting_at?->toDateTimeString(),
            'submitted_at' => $intervention->submitted_at?->toDateTimeString(),
            'approved_at' => $intervention->approved_at?->toDateTimeString(),
            'rejected_at' => $intervention->rejected_at?->toDateTimeString(),
            'rejection_reason' => $intervention->rejection_reason,
            'paid_at' => $intervention->paid_at?->toDateTimeString(),
            'costs' => $canViewCosts
                ? $intervention->costs->map(function ($cost): array {
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
            'tickets' => $intervention->tickets->map(function (MaintenanceTicket $ticket): array {
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
            'items' => $intervention->items->map(function (MaintenanceInterventionItem $item): array {
                return [
                    'id' => $item->id,
                    'stock_item' => $item->stockItem?->only(['id', 'name', 'sku', 'unit', 'default_purchase_price']),
                    'storage_location' => $item->storageLocation?->only(['id', 'name']),
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'total_cost' => (float) $item->total_cost,
                    'notes' => $item->notes,
                ];
            })->values(),
            'stock_consumption_total' => (float) $intervention->items->sum('total_cost'),
        ];
    }

    private function resolveHotel(User $user): Hotel
    {
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        return Hotel::query()
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail($hotelId);
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

    private function ensureWorkDoneOnSubmit(MaintenanceIntervention $intervention): void
    {
        $missing = $intervention->tickets()
            ->where(function ($query): void {
                $query->whereNull('maintenance_intervention_ticket.work_done')
                    ->orWhere('maintenance_intervention_ticket.work_done', '');
            })
            ->pluck('maintenance_tickets.id')
            ->all();

        if ($missing !== []) {
            response()->json([
                'message' => 'Veuillez renseigner les travaux effectués pour chaque ticket.',
                'errors' => [
                    'tickets' => ['Travaux effectués requis pour chaque ticket.'],
                ],
            ], 422)->throwResponse();
        }
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

    private function findTicket(MaintenanceIntervention $intervention, int $ticketId): MaintenanceTicket
    {
        return MaintenanceTicket::query()
            ->where('tenant_id', $intervention->tenant_id)
            ->where('hotel_id', $intervention->hotel_id)
            ->findOrFail($ticketId);
    }

    private function syncTickets(
        MaintenanceIntervention $intervention,
        ?array $tickets,
        string $tenantId,
        int $hotelId,
    ): void {
        if ($tickets === null) {
            return;
        }

        $payload = [];

        foreach ($tickets as $ticketData) {
            $ticketId = (int) ($ticketData['maintenance_ticket_id'] ?? 0);

            if ($ticketId === 0) {
                continue;
            }

            $payload[$ticketId] = [
                'tenant_id' => $tenantId,
                'hotel_id' => $hotelId,
                'work_done' => $ticketData['work_done'] ?? null,
                'labor_cost' => $ticketData['labor_cost'] ?? 0,
                'parts_cost' => $ticketData['parts_cost'] ?? 0,
            ];
        }

        if ($payload === []) {
            $intervention->tickets()->sync([]);

            return;
        }

        $intervention->tickets()->sync($payload);
    }

    private function syncLegacyCosts(MaintenanceIntervention $intervention, array $data): void
    {
        if ($intervention->costs()->exists()) {
            $intervention->recalcTotalsFromCosts();

            return;
        }

        $labor = (float) ($data['labor_cost'] ?? 0);
        $parts = (float) ($data['parts_cost'] ?? 0);

        if ($labor <= 0 && $parts <= 0) {
            return;
        }

        $currency = $data['currency'] ?? $intervention->currency ?? 'XAF';
        $lines = [];

        if ($labor > 0) {
            $lines[] = [
                'tenant_id' => $intervention->tenant_id,
                'hotel_id' => $intervention->hotel_id,
                'maintenance_intervention_id' => $intervention->id,
                'cost_type' => 'labor',
                'label' => 'Main d’œuvre',
                'quantity' => 1,
                'unit_price' => $labor,
                'total_amount' => $labor,
                'currency' => $currency,
                'notes' => null,
                'created_by_user_id' => $intervention->created_by_user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($parts > 0) {
            $lines[] = [
                'tenant_id' => $intervention->tenant_id,
                'hotel_id' => $intervention->hotel_id,
                'maintenance_intervention_id' => $intervention->id,
                'cost_type' => 'parts',
                'label' => 'Pièces',
                'quantity' => 1,
                'unit_price' => $parts,
                'total_amount' => $parts,
                'currency' => $currency,
                'notes' => null,
                'created_by_user_id' => $intervention->created_by_user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($lines !== []) {
            $intervention->costs()->createMany($lines);
            $intervention->recalcTotalsFromCosts();
        }
    }

    private function isManagerOrOwner(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['owner', 'manager']);
    }

    private function determineUnitCost(array $data, ?User $user, StockItem $item): float
    {
        if ($this->canOverrideUnitCost($user)
            && array_key_exists('unit_cost', $data)
            && $data['unit_cost'] !== null
        ) {
            return (float) $data['unit_cost'];
        }

        return (float) ($item->default_purchase_price ?? 0);
    }

    private function canViewCosts(?User $user): bool
    {
        return $user?->can('maintenance.costs.view')
            || $user?->can('maintenance.costs.edit')
            || $user?->can('maintenance.interventions.costs.manage')
            || $user?->hasAnyRole(['owner', 'manager']) === true;
    }

    private function canEditCosts(?User $user): bool
    {
        return $user?->can('maintenance.costs.edit')
            || $user?->can('maintenance.interventions.costs.manage')
            || $user?->hasAnyRole(['owner', 'manager']) === true;
    }

    private function canOverrideUnitCost(?User $user): bool
    {
        return $user?->can('maintenance.costs.override_unit_cost')
            || $user?->hasAnyRole(['owner', 'manager']) === true;
    }

    private function movementPayload(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'movement_type' => $movement->movement_type,
            'occurred_at' => $movement->occurred_at?->toDateTimeString(),
            'from_location' => $movement->fromLocation?->only(['id', 'name']),
            'to_location' => $movement->toLocation?->only(['id', 'name']),
            'reference' => $this->movementReferencePayload($movement),
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
        ];
    }

    private function movementReferencePayload(StockMovement $movement): ?array
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
