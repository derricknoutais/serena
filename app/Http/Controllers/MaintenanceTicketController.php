<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceTicketRequest;
use App\Http\Requests\UpdateMaintenanceTicketRequest;
use App\Models\MaintenanceTicket;
use App\Models\Room;
use App\Models\User;
use App\Services\HousekeepingService;
use App\Services\RoomStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $tickets = $ticketsQuery
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MaintenanceTicket $ticket): array => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'status' => $ticket->status,
                'severity' => $ticket->severity,
                'description' => $ticket->description,
                'opened_at' => optional($ticket->opened_at)?->toDateTimeString(),
                'closed_at' => optional($ticket->closed_at)?->toDateTimeString(),
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

        return Inertia::render('Maintenance/Index', [
            'tickets' => $tickets,
            'filters' => [
                'status' => $statusFilter ?? 'open',
            ],
            'statusOptions' => [
                MaintenanceTicket::STATUS_OPEN,
                MaintenanceTicket::STATUS_IN_PROGRESS,
                MaintenanceTicket::STATUS_RESOLVED,
                MaintenanceTicket::STATUS_CLOSED,
                'all',
            ],
            'assignableUsers' => $assignableUsers,
            'permissions' => [
                'canUpdateStatus' => $canUpdate,
                'canAssign' => $canHandle,
                'canClose' => $canClose,
            ],
        ]);
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

            if (in_array($data['status'], [MaintenanceTicket::STATUS_RESOLVED, MaintenanceTicket::STATUS_CLOSED], true)) {
                $ticket->closed_at = now();
            } else {
                $ticket->closed_at = null;
            }
        }

        $ticket->save();

        $room = $ticket->room()->firstOrFail();
        $changes = array_intersect_key($ticket->getChanges(), array_flip([
            'status',
            'severity',
            'blocks_sale',
            'assigned_to_user_id',
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
            'reported_by' => $ticket->reportedBy?->only(['id', 'name']),
            'assigned_to' => $ticket->assignedTo?->only(['id', 'name']),
        ];
    }
}
