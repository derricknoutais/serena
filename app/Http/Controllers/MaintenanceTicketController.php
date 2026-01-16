<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloseMaintenanceTicketRequest;
use App\Http\Requests\StoreMaintenanceTicketRequest;
use App\Http\Requests\UpdateMaintenanceTicketRequest;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceTicket;
use App\Models\MaintenanceType;
use App\Models\Room;
use App\Models\User;
use App\Services\HousekeepingService;
use App\Services\RoomStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceTicketController extends Controller
{
    public function __construct(
        private readonly RoomStateMachine $roomStateMachine,
        private readonly HousekeepingService $housekeepingService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MaintenanceTicket::class);

        /** @var User $user */
        $user = $request->user();
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        $statusOptions = [
            MaintenanceTicket::STATUS_OPEN,
            MaintenanceTicket::STATUS_IN_PROGRESS,
            MaintenanceTicket::STATUS_RESOLVED,
            MaintenanceTicket::STATUS_CLOSED,
            'all',
        ];

        $statusFilter = $request->string('status')->toString();
        $hasExplicitFilter = $request->has('status');
        $roomFilter = $request->string('room_id')->toString();
        $typeFilter = $request->integer('maintenance_type_id');
        $severityFilter = $request->string('severity')->toString();
        $blocksSaleFilter = $request->has('blocks_sale') ? $request->boolean('blocks_sale') : null;
        $tab = $request->string('tab')->toString();

        if ($statusFilter !== null && ! in_array($statusFilter, $statusOptions, true)) {
            $statusFilter = null;
        }

        $ticketsQuery = MaintenanceTicket::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->with([
                'room.roomType:id,name',
                'reportedBy:id,name',
                'assignedTo:id,name',
                'type:id,name',
            ])
            ->orderByDesc('opened_at')
            ->orderByDesc('id');

        if ($statusFilter !== 'all') {
            if ($statusFilter === null && ! $hasExplicitFilter) {
                $ticketsQuery->whereIn('status', [
                    MaintenanceTicket::STATUS_OPEN,
                    MaintenanceTicket::STATUS_IN_PROGRESS,
                ]);
            } elseif ($statusFilter !== null) {
                $ticketsQuery->where('status', $statusFilter);
            }
        }

        if ($roomFilter !== '') {
            $ticketsQuery->where('room_id', $roomFilter);
        }

        if ($typeFilter) {
            $ticketsQuery->where('maintenance_type_id', $typeFilter);
        }

        if ($severityFilter !== null && in_array($severityFilter, [
            MaintenanceTicket::SEVERITY_LOW,
            MaintenanceTicket::SEVERITY_MEDIUM,
            MaintenanceTicket::SEVERITY_HIGH,
            MaintenanceTicket::SEVERITY_CRITICAL,
        ], true)) {
            $ticketsQuery->where('severity', $severityFilter);
        }

        if ($blocksSaleFilter !== null) {
            $ticketsQuery->where('blocks_sale', $blocksSaleFilter);
        }

        $tickets = $ticketsQuery
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MaintenanceTicket $ticket): array => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'status' => $ticket->status,
                'severity' => $ticket->severity,
                'description' => $ticket->description,
                'blocks_sale' => (bool) $ticket->blocks_sale,
                'opened_at' => optional($ticket->opened_at)?->toDateTimeString(),
                'closed_at' => optional($ticket->closed_at)?->toDateTimeString(),
                'maintenance_type' => $ticket->type?->only(['id', 'name']),
                'room' => $ticket->room ? [
                    'id' => $ticket->room->id,
                    'number' => $ticket->room->number,
                    'room_type_name' => $ticket->room->roomType?->name,
                ] : null,
                'reported_by' => $ticket->reportedBy?->only(['id', 'name']),
                'assigned_to' => $ticket->assignedTo?->only(['id', 'name']),
            ]);

        $assignableUsers = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', [
                'owner',
                'manager',
                'maintenance',
                'superadmin',
            ]))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $assignable): array => [
                'id' => $assignable->id,
                'name' => $assignable->name,
            ]);

        $canHandle = $user->hasRole(['owner', 'manager', 'maintenance', 'superadmin']);
        $canUpdate = $user->can('maintenance_tickets.update');
        $canClose = $user->can('maintenance_tickets.close');

        $interventionStatusOptions = [
            MaintenanceIntervention::STATUS_DRAFT,
            MaintenanceIntervention::STATUS_SUBMITTED,
            MaintenanceIntervention::STATUS_APPROVED,
            MaintenanceIntervention::STATUS_REJECTED,
            MaintenanceIntervention::STATUS_PAID,
            'all',
        ];

        $interventionStatus = $request->string('intervention_status')->toString();
        $technicianFilter = $request->integer('technician_id');
        $interventionFrom = $request->string('intervention_from')->toString();
        $interventionTo = $request->string('intervention_to')->toString();

        $interventionsQuery = MaintenanceIntervention::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->with(['technician:id,name', 'tickets.room:id,number'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($interventionStatus !== '' && $interventionStatus !== 'all' && in_array($interventionStatus, $interventionStatusOptions, true)) {
            $interventionsQuery->where('accounting_status', $interventionStatus);
        }

        if ($technicianFilter) {
            $interventionsQuery->where('technician_id', $technicianFilter);
        }

        if ($interventionFrom !== '') {
            $interventionsQuery->whereDate('created_at', '>=', $interventionFrom);
        }

        if ($interventionTo !== '') {
            $interventionsQuery->whereDate('created_at', '<=', $interventionTo);
        }

        if ($roomFilter !== '') {
            $interventionsQuery->whereHas('tickets', function ($query) use ($roomFilter): void {
                $query->where('room_id', $roomFilter);
            });
        }

        $interventions = $interventionsQuery
            ->paginate(10, ['*'], 'interventions_page')
            ->withQueryString()
            ->through(fn (MaintenanceIntervention $intervention): array => [
                'id' => $intervention->id,
                'technician' => $intervention->technician?->only(['id', 'name']),
                'accounting_status' => $intervention->accounting_status,
                'started_at' => $intervention->started_at?->toDateTimeString(),
                'ended_at' => $intervention->ended_at?->toDateTimeString(),
                'total_cost' => (float) $intervention->total_cost,
                'estimated_total_amount' => (float) ($intervention->estimated_total_amount ?? $intervention->total_cost),
                'currency' => $intervention->currency,
                'rooms' => $intervention->tickets->map(fn ($ticket) => $ticket->room?->number)->filter()->unique()->values(),
            ]);

        $rooms = Room::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->orderBy('number')
            ->get(['id', 'number', 'floor']);

        $openTickets = MaintenanceTicket::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->whereIn('status', [
                MaintenanceTicket::STATUS_OPEN,
                MaintenanceTicket::STATUS_IN_PROGRESS,
            ])
            ->with(['room:id,number', 'type:id,name'])
            ->orderByDesc('opened_at')
            ->get()
            ->map(fn (MaintenanceTicket $ticket): array => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'status' => $ticket->status,
                'room_number' => $ticket->room?->number,
                'maintenance_type' => $ticket->type?->only(['id', 'name']),
            ]);

        if (! in_array($tab, ['tickets', 'interventions'], true)) {
            $tab = 'tickets';
        }

        $types = MaintenanceType::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return Inertia::render('Maintenance/Index', [
            'tickets' => $tickets,
            'filters' => [
                'status' => $statusFilter ?? 'open',
                'room_id' => $roomFilter,
                'maintenance_type_id' => $typeFilter,
                'severity' => $severityFilter,
                'blocks_sale' => $blocksSaleFilter,
                'intervention_status' => $interventionStatus,
                'technician_id' => $technicianFilter,
                'intervention_from' => $interventionFrom,
                'intervention_to' => $interventionTo,
            ],
            'activeTab' => $tab,
            'statusOptions' => [
                MaintenanceTicket::STATUS_OPEN,
                MaintenanceTicket::STATUS_IN_PROGRESS,
                MaintenanceTicket::STATUS_RESOLVED,
                MaintenanceTicket::STATUS_CLOSED,
                'all',
            ],
            'interventionStatusOptions' => $interventionStatusOptions,
            'assignableUsers' => $assignableUsers,
            'maintenanceTypes' => $types,
            'rooms' => $rooms,
            'technicians' => $this->techniciansList($user->tenant_id, $hotelId),
            'openTickets' => $openTickets,
            'interventions' => $interventions,
            'permissions' => [
                'canUpdateStatus' => $canUpdate,
                'canAssign' => $canHandle,
                'canClose' => $canClose,
            ],
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:int,name:string}>
     */
    private function techniciansList(string $tenantId, int $hotelId)
    {
        return \App\Models\Technician::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function store(StoreMaintenanceTicketRequest $request): JsonResponse
    {
        $this->authorize('create', MaintenanceTicket::class);

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        $room = Room::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->with('roomType')
            ->findOrFail($data['room_id']);

        $canOverrideBlocks = $user->hasAnyRole(['owner', 'manager']);
        $blocksSaleInput = array_key_exists('blocks_sale', $data) ? (bool) $data['blocks_sale'] : null;

        $ticket = MaintenanceTicket::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotelId,
            'room_id' => $room->id,
            'maintenance_type_id' => $this->resolveMaintenanceTypeId(
                $user->tenant_id,
                $hotelId,
                $data['maintenance_type_id'] ?? null,
            ),
            'reported_by_user_id' => $user->id,
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'status' => MaintenanceTicket::STATUS_OPEN,
            'severity' => $data['severity'],
            'blocks_sale' => $this->resolveBlocksSale($data['severity'], $blocksSaleInput, $canOverrideBlocks),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'opened_at' => now(),
        ]);

        activity('maintenance')
            ->performedOn($ticket)
            ->causedBy($user)
            ->withProperties([
                'room_id' => $room->id,
                'maintenance_type_id' => $ticket->maintenance_type_id,
                'severity' => $ticket->severity,
                'blocks_sale' => (bool) $ticket->blocks_sale,
                'status' => $ticket->status,
            ])
            ->event('created')
            ->log('created');

        if ($room->status === Room::STATUS_OCCUPIED && $ticket->blocks_sale) {
            $room->block_sale_after_checkout = true;
            $room->save();
        }

        return response()->json([
            'ticket' => $this->ticketPayload($ticket->fresh(['assignedTo:id,name', 'reportedBy:id,name'])),
        ]);
    }

    public function update(UpdateMaintenanceTicketRequest $request, MaintenanceTicket $maintenanceTicket): JsonResponse
    {
        $this->authorize('update', $maintenanceTicket);

        $ticket = $maintenanceTicket;
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $canOverrideBlocks = $user->hasAnyRole(['owner', 'manager']);

        if (isset($data['status'])) {
            $this->ensureStatusPermission($user, $data['status']);
        }

        if (array_key_exists('blocks_sale', $data) && ! $canOverrideBlocks) {
            abort(403);
        }

        if (array_key_exists('assigned_to_user_id', $data)) {
            $ticket->assigned_to_user_id = $data['assigned_to_user_id'];
        }

        if (array_key_exists('maintenance_type_id', $data)) {
            $ticket->maintenance_type_id = $data['maintenance_type_id'];
        }

        if (array_key_exists('description', $data)) {
            $ticket->description = $data['description'];
        }

        if (array_key_exists('severity', $data)) {
            $ticket->severity = $data['severity'];
        }

        if (array_key_exists('blocks_sale', $data)) {
            $ticket->blocks_sale = (bool) $data['blocks_sale'];
        }

        if (isset($data['status'])) {
            $ticket->status = $data['status'];

            if ($data['status'] === MaintenanceTicket::STATUS_RESOLVED) {
                $ticket->resolved_at = $ticket->resolved_at ?? now();
            }

            if ($data['status'] === MaintenanceTicket::STATUS_CLOSED) {
                $ticket->closed_by_user_id = $user->id;
            }

            if (in_array($data['status'], [MaintenanceTicket::STATUS_RESOLVED, MaintenanceTicket::STATUS_CLOSED], true)) {
                $ticket->closed_at = now();
            } else {
                $ticket->closed_at = null;
                $ticket->closed_by_user_id = null;
                $ticket->resolved_at = null;
            }
        }

        $ticket->save();

        $room = $ticket->room()->firstOrFail();
        $changes = array_intersect_key($ticket->getChanges(), array_flip([
            'status',
            'severity',
            'blocks_sale',
            'assigned_to_user_id',
            'maintenance_type_id',
            'resolved_at',
            'closed_by_user_id',
        ]));

        if ($changes !== []) {
            activity('maintenance')
                ->performedOn($ticket)
                ->causedBy($user)
                ->withProperties($changes)
                ->event('updated')
                ->log('updated');
        }

        $hasBlockingOpenTickets = $room->maintenanceTickets()
            ->whereIn('status', [
                MaintenanceTicket::STATUS_OPEN,
                MaintenanceTicket::STATUS_IN_PROGRESS,
            ])
            ->where('blocks_sale', true)
            ->exists();

        if ($room->status === Room::STATUS_OCCUPIED && $hasBlockingOpenTickets) {
            $room->block_sale_after_checkout = true;
            $room->save();
        }

        if (! $hasBlockingOpenTickets) {
            $room->block_sale_after_checkout = false;

            if ($room->status === Room::STATUS_OUT_OF_ORDER && $request->boolean('restore_room_status')) {
                $this->roomStateMachine->markAvailable($room);
                $this->housekeepingService->forceRoomStatus($room, Room::HK_STATUS_DIRTY, $user);
            }

            $room->save();
        }

        return response()->json([
            'ticket' => $this->ticketPayload($ticket->fresh(['assignedTo:id,name', 'reportedBy:id,name'])),
        ]);
    }

    public function close(CloseMaintenanceTicketRequest $request, MaintenanceTicket $maintenanceTicket): JsonResponse
    {
        $this->authorize('close', $maintenanceTicket);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();

        $maintenanceTicket->status = MaintenanceTicket::STATUS_CLOSED;
        $maintenanceTicket->closed_at = isset($data['closed_at'])
            ? Carbon::parse($data['closed_at'])
            : now();
        $maintenanceTicket->closed_by_user_id = $user->id;
        $maintenanceTicket->save();

        $room = $maintenanceTicket->room()->firstOrFail();
        $hasBlockingOpenTickets = $room->maintenanceTickets()
            ->whereIn('status', [
                MaintenanceTicket::STATUS_OPEN,
                MaintenanceTicket::STATUS_IN_PROGRESS,
            ])
            ->where('blocks_sale', true)
            ->exists();

        if ($room->status === Room::STATUS_OCCUPIED && $hasBlockingOpenTickets) {
            $room->block_sale_after_checkout = true;
            $room->save();
        }

        if (! $hasBlockingOpenTickets) {
            $room->block_sale_after_checkout = false;

            if ($room->status === Room::STATUS_OUT_OF_ORDER && $request->boolean('restore_room_status')) {
                $this->roomStateMachine->markAvailable($room);
                $this->housekeepingService->forceRoomStatus($room, Room::HK_STATUS_DIRTY, $user);
            }

            $room->save();
        }

        activity('maintenance')
            ->performedOn($maintenanceTicket)
            ->causedBy($user)
            ->withProperties([
                'status' => $maintenanceTicket->status,
                'closed_at' => $maintenanceTicket->closed_at?->toDateTimeString(),
                'closed_by_user_id' => $user->id,
            ])
            ->event('closed')
            ->log('closed');

        return response()->json([
            'ticket' => $this->ticketPayload($maintenanceTicket->fresh(['assignedTo:id,name', 'reportedBy:id,name'])),
        ]);
    }

    private function ensureStatusPermission(User $user, string $status): void
    {
        $isClosing = in_array($status, [MaintenanceTicket::STATUS_RESOLVED, MaintenanceTicket::STATUS_CLOSED], true);
        $requiredPermission = $isClosing ? 'maintenance_tickets.close' : 'maintenance_tickets.update';

        abort_if(! $user->can($requiredPermission), 403);
    }

    private function resolveBlocksSale(string $severity, ?bool $blocksSaleInput, bool $canOverrideBlocks): bool
    {
        if ($blocksSaleInput !== null) {
            abort_if(! $canOverrideBlocks, 403);

            return $blocksSaleInput;
        }

        return MaintenanceTicket::defaultBlocksSaleFromSeverity($severity);
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(MaintenanceTicket $ticket): array
    {
        $ticket->loadMissing('type:id,name');

        return [
            'id' => $ticket->id,
            'room_id' => $ticket->room_id,
            'status' => $ticket->status,
            'severity' => $ticket->severity,
            'blocks_sale' => (bool) $ticket->blocks_sale,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'opened_at' => optional($ticket->opened_at)?->toDateTimeString(),
            'closed_at' => optional($ticket->closed_at)?->toDateTimeString(),
            'resolved_at' => optional($ticket->resolved_at)?->toDateTimeString(),
            'maintenance_type' => $ticket->type?->only(['id', 'name']),
            'reported_by' => $ticket->reportedBy?->only(['id', 'name']),
            'assigned_to' => $ticket->assignedTo?->only(['id', 'name']),
            'closed_by' => $ticket->closer?->only(['id', 'name']),
        ];
    }

    private function resolveMaintenanceTypeId(string $tenantId, int $hotelId, ?int $typeId): ?int
    {
        if ($typeId) {
            return $typeId;
        }

        $type = MaintenanceType::query()->firstOrCreate([
            'tenant_id' => $tenantId,
            'hotel_id' => $hotelId,
            'name' => 'Autre',
        ], [
            'is_active' => true,
        ]);

        return $type->id;
    }
}
