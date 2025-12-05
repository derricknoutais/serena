<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceTicketRequest;
use App\Http\Requests\UpdateMaintenanceTicketRequest;
use App\Models\MaintenanceTicket;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceTicketController extends Controller
{
    public function __construct(private readonly RoomStateMachine $roomStateMachine) {}

    public function index(Request $request): Response
    {
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
                'canUpdateStatus' => $canHandle,
                'canAssign' => $canHandle,
            ],
        ]);
    }

    public function store(StoreMaintenanceTicketRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        $room = Room::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->with('roomType')
            ->findOrFail($data['room_id']);

        $this->ensureNoActiveTicket($room);

        $ticket = MaintenanceTicket::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotelId,
            'room_id' => $room->id,
            'reported_by_user_id' => $user->id,
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'status' => MaintenanceTicket::STATUS_OPEN,
            'severity' => $data['severity'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'opened_at' => now(),
        ]);

        if ($room->status === Room::STATUS_AVAILABLE) {
            $this->roomStateMachine->markOutOfOrder($room);
        }

        return response()->json([
            'ticket' => $this->ticketPayload($ticket->fresh(['assignedTo:id,name', 'reportedBy:id,name'])),
        ]);
    }

    public function update(UpdateMaintenanceTicketRequest $request, MaintenanceTicket $maintenanceTicket): JsonResponse
    {
        $ticket = $maintenanceTicket;
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (isset($data['status'])) {
            $this->ensureStatusPermission($user, $data['status']);
        }

        if (array_key_exists('assigned_to_user_id', $data)) {
            $ticket->assigned_to_user_id = $data['assigned_to_user_id'];
        }

        if (array_key_exists('description', $data)) {
            $ticket->description = $data['description'];
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

        if (isset($data['status'])) {
            if (in_array($data['status'], [MaintenanceTicket::STATUS_RESOLVED, MaintenanceTicket::STATUS_CLOSED], true)) {
                $shouldRestore = $request->boolean('restore_room_status');

                if ($shouldRestore && $room->status === Room::STATUS_OUT_OF_ORDER) {
                    $this->roomStateMachine->markAvailable($room);
                    $room->hk_status = 'dirty';
                    $room->save();
                }
            } elseif (in_array($data['status'], [MaintenanceTicket::STATUS_OPEN, MaintenanceTicket::STATUS_IN_PROGRESS], true)) {
                if ($room->status === Room::STATUS_AVAILABLE) {
                    $this->roomStateMachine->markOutOfOrder($room);
                }
            }
        }

        return response()->json([
            'ticket' => $this->ticketPayload($ticket->fresh(['assignedTo:id,name', 'reportedBy:id,name'])),
        ]);
    }

    private function ensureNoActiveTicket(Room $room): void
    {
        $hasActiveTicket = $room->maintenanceTickets()
            ->whereIn('status', [
                MaintenanceTicket::STATUS_OPEN,
                MaintenanceTicket::STATUS_IN_PROGRESS,
            ])
            ->exists();

        if ($hasActiveTicket) {
            throw ValidationException::withMessages([
                'room_id' => 'Un ticket de maintenance est déjà en cours pour cette chambre.',
            ]);
        }
    }

    private function ensureStatusPermission(User $user, string $status): void
    {
        $advancedRoles = ['owner', 'manager', 'maintenance', 'superadmin'];
        $standardRoles = array_merge($advancedRoles, ['receptionist']);

        if (in_array($status, [MaintenanceTicket::STATUS_RESOLVED, MaintenanceTicket::STATUS_CLOSED], true)) {
            if (! $user->hasRole($advancedRoles)) {
                abort(403);
            }

            return;
        }

        if (! $user->hasRole($standardRoles)) {
            abort(403);
        }
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
            'title' => $ticket->title,
            'description' => $ticket->description,
            'opened_at' => optional($ticket->opened_at)?->toDateTimeString(),
            'closed_at' => optional($ticket->closed_at)?->toDateTimeString(),
            'reported_by' => $ticket->reportedBy?->only(['id', 'name']),
            'assigned_to' => $ticket->assignedTo?->only(['id', 'name']),
        ];
    }
}
