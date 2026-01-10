<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FinishInspectionRequest;
use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\HousekeepingPriorityService;
use App\Services\HousekeepingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HousekeepingController extends Controller
{
    public function __construct(
        private readonly HousekeepingService $housekeepingService,
        private readonly HousekeepingPriorityService $priorityService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $hotelId = $this->resolveHotelId($user);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        $this->priorityService->syncHotelTasks($user->tenant_id, $hotelId);

        $roomId = (string) $request->query('room', '');
        $room = null;

        if ($roomId !== '') {
            $room = Room::query()->find($roomId);
            if ($room) {
                $this->authorizeRoomAccess($request, $room);
                $room = $this->roomPayload($room);
            }
        }

        $tasks = $this->loadHotelTasks($user->tenant_id, $hotelId);

        $arrivalMap = $this->arrivalMapForRooms(
            $tasks->pluck('room_id')->filter()->unique()->all(),
            $user->tenant_id,
            $hotelId,
        );

        return Inertia::render('Housekeeping/Index', [
            'room' => $room,
            'canManageHousekeeping' => $this->canManage($request),
            'tasks' => $this->tasksPayload($tasks, $arrivalMap),
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $hotelId = $this->resolveHotelId($user);

        if ($hotelId === 0) {
            return response()->json([
                'tasks' => [],
            ]);
        }

        $tasks = $this->loadHotelTasks($user->tenant_id, $hotelId);
        $arrivalMap = $this->arrivalMapForRooms(
            $tasks->pluck('room_id')->filter()->unique()->all(),
            $user->tenant_id,
            $hotelId,
        );

        return response()->json([
            'tasks' => $this->tasksPayload($tasks, $arrivalMap),
        ]);
    }

    public function show(Request $request, Room $room): JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeRoomAccess($request, $room);

        return response()->json([
            'room' => $this->roomPayload($room),
        ]);
    }

    public function updateStatus(Request $request, Room $room): JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeRoomAccess($request, $room);

        $data = $request->validate([
            'hk_status' => ['required', 'in:dirty,cleaning,awaiting_inspection,inspected,redo,in_use'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        match ($data['hk_status']) {
            'inspected', 'in_use' => Gate::authorize('housekeeping.mark_inspected'),
            'dirty', 'redo' => Gate::authorize('housekeeping.mark_dirty'),
            default => Gate::authorize('housekeeping.mark_clean'),
        };

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->housekeepingService->updateRoomStatus(
            $room,
            $data['hk_status'],
            $user,
            $data['note'] ?? null,
        );

        return response()->json([
            'room' => $this->roomPayload($room),
        ]);
    }

    public function startTask(Request $request, Room $room): JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeRoomAccess($request, $room);

        $task = $this->currentCleaningTask($room);

        if (! $task) {
            return response()->json([
                'message' => 'Aucune tâche en attente pour cette chambre.',
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->housekeepingService->startTask($task, $user);

        return response()->json([
            'room' => $this->roomPayload($room->fresh()),
        ]);
    }

    public function joinTask(Request $request, Room $room): JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeRoomAccess($request, $room);

        $task = $this->currentCleaningTask($room);

        if (! $task || $task->status !== HousekeepingTask::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'Aucune tâche en cours pour cette chambre.',
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->housekeepingService->joinTask($task, $user);

        return response()->json([
            'room' => $this->roomPayload($room->fresh()),
        ]);
    }

    public function finishTask(Request $request, Room $room): JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeRoomAccess($request, $room);

        $task = $this->currentCleaningTask($room);

        if (! $task || $task->status !== HousekeepingTask::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'Aucune tâche en cours pour cette chambre.',
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->housekeepingService->finishCleaning($task, $user);

        return response()->json([
            'room' => $this->roomPayload($room->fresh()),
        ]);
    }

    public function startInspection(Request $request, Room $room): JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeRoomAccess($request, $room);
        Gate::authorize('housekeeping.mark_inspected');

        $task = $this->currentInspectionTask($room);

        if (! $task) {
            return response()->json([
                'message' => 'Aucune inspection en attente pour cette chambre.',
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->housekeepingService->startInspection($task, $user);

        return response()->json([
            'room' => $this->roomPayload($room->fresh()),
        ]);
    }

    public function finishInspection(FinishInspectionRequest $request, Room $room): JsonResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeRoomAccess($request, $room);
        Gate::authorize('housekeeping.mark_inspected');

        $task = $this->currentInspectionTask($room);

        if (! $task || $task->status !== HousekeepingTask::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'Aucune inspection en cours pour cette chambre.',
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $data = $request->validated();

        $this->housekeepingService->finishInspection($task, $user, $data['items'] ?? []);

        return response()->json([
            'room' => $this->roomPayload($room->fresh()),
        ]);
    }

    private function roomPayload(Room $room): array
    {
        $room->loadMissing('roomType', 'hotel');

        $today = Carbon::today();
        $currentReservation = Reservation::query()
            ->where('room_id', $room->id)
            ->whereDate('check_out_date', '>=', $today)
            ->whereIn('status', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_IN_HOUSE,
            ])
            ->with('guest')
            ->orderBy('check_in_date')
            ->first();

        $occupancyState = 'Libre';
        if ($currentReservation) {
            $occupancyState = match ($currentReservation->status) {
                Reservation::STATUS_IN_HOUSE => 'En séjour',
                Reservation::STATUS_CONFIRMED, Reservation::STATUS_PENDING => $currentReservation->check_in_date?->isToday()
                    ? 'Arrivée aujourd’hui'
                    : 'Réservation future',
                default => 'Réservation en cours',
            };

            if ($currentReservation->check_out_date?->isToday()) {
                $occupancyState = 'Départ aujourd’hui';
            }
        }

        $arrivalToday = $currentReservation
            && in_array($currentReservation->status, [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED], true)
            && $currentReservation->check_in_date?->isToday();

        $currentTask = $this->currentTask($room);
        $lastTask = $this->lastTask($room);

        if ($currentTask) {
            $currentTask->setRelation('room', $room);
        }

        if ($lastTask) {
            $lastTask->setRelation('room', $room);
        }

        $hkPriority = $currentTask
            ? $this->priorityService->computePriorityForTask($currentTask, $arrivalToday)
            : $this->priorityService->computePriorityForRoom($room, $arrivalToday);

        return [
            'id' => $room->id,
            'number' => $room->number,
            'floor' => $room->floor,
            'room_type' => $room->roomType?->name,
            'hk_status' => $room->hk_status,
            'hk_status_label' => $this->hkStatusLabel($room->hk_status),
            'is_occupied' => $room->isOccupiedNow(),
            'hk_priority' => $hkPriority,
            'arrival_today' => (bool) $arrivalToday,
            'housekeeping_task' => $this->taskPayload($currentTask, $room, $arrivalToday),
            'last_housekeeping_task' => $this->taskPayload($lastTask, $room, $arrivalToday),
            'last_inspection' => $this->inspectionSummary($room),
            'occupancy' => [
                'state' => $occupancyState,
                'reservation' => $currentReservation ? [
                    'id' => $currentReservation->id,
                    'code' => $currentReservation->code,
                    'status' => $currentReservation->status,
                    'guest_name' => $currentReservation->guest
                        ? trim(($currentReservation->guest->first_name ?? '').' '.($currentReservation->guest->last_name ?? ''))
                        : null,
                    'check_in_date' => optional($currentReservation->check_in_date)->toDateString(),
                    'check_out_date' => optional($currentReservation->check_out_date)->toDateString(),
                ] : null,
            ],
        ];
    }

    private function hkStatusLabel(string $status): string
    {
        return match ($status) {
            'dirty' => 'Sale',
            'cleaning' => 'En cours',
            'awaiting_inspection' => 'En attente d’inspection',
            'redo' => 'À refaire',
            'inspected' => 'Inspectée',
            'in_use' => 'En usage',
            default => 'Propre',
        };
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function authorizeRoomAccess(Request $request, Room $room): void
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $hotelId = $this->resolveHotelId($user);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        if ($room->tenant_id !== $user->tenant_id || (int) $room->hotel_id !== $hotelId) {
            abort(403);
        }
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user?->can('housekeeping.view') ?? false;
    }

    private function resolveHotelId(\App\Models\User $user): int
    {
        return (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);
    }

    private function currentTask(Room $room): ?HousekeepingTask
    {
        return HousekeepingTask::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('hotel_id', $room->hotel_id)
            ->where('room_id', $room->id)
            ->whereIn('type', [
                HousekeepingTask::TYPE_CLEANING,
                HousekeepingTask::TYPE_INSPECTION,
                HousekeepingTask::TYPE_REDO_CLEANING,
                HousekeepingTask::TYPE_REDO_INSPECTION,
            ])
            ->whereIn('status', [
                HousekeepingTask::STATUS_PENDING,
                HousekeepingTask::STATUS_IN_PROGRESS,
            ])
            ->orderByRaw("case status when 'in_progress' then 0 else 1 end")
            ->orderByRaw("case when type in ('inspection', 'redo-inspection') then 0 else 1 end")
            ->orderByDesc('created_at')
            ->first();
    }

    private function lastTask(Room $room): ?HousekeepingTask
    {
        return HousekeepingTask::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('hotel_id', $room->hotel_id)
            ->where('room_id', $room->id)
            ->where('status', HousekeepingTask::STATUS_DONE)
            ->orderByDesc('ended_at')
            ->first();
    }

    private function taskPayload(?HousekeepingTask $task, Room $room, ?bool $arrivalToday = null): ?array
    {
        if (! $task) {
            return null;
        }

        $task->loadMissing('participants:id,name', 'checklistItems');
        $checklist = null;

        if (in_array($task->type, [
            HousekeepingTask::TYPE_INSPECTION,
            HousekeepingTask::TYPE_REDO_INSPECTION,
        ], true)) {
            $checklist = $this->inspectionChecklistPayload($room, $task);
        }

        $priority = $this->priorityService->computePriorityForTask($task, $arrivalToday);

        return [
            'id' => $task->id,
            'type' => $task->type,
            'status' => $task->status,
            'priority' => $priority,
            'started_at' => $this->formatDateTimeLocal($task->started_at, $room),
            'ended_at' => $this->formatDateTimeLocal($task->ended_at, $room),
            'created_at' => $this->formatDateTimeLocal($task->created_at, $room),
            'duration_seconds' => $task->duration_seconds,
            'outcome' => $task->outcome,
            'checklist' => $checklist,
            'participants' => $task->participants
                ->map(fn ($participant): array => [
                    'id' => $participant->id,
                    'name' => $participant->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, HousekeepingTask>  $tasks
     * @return array<int, array<string, mixed>>
     */
    private function tasksPayload($tasks, array $arrivalMap = []): array
    {
        return $tasks->map(function (HousekeepingTask $task): array {
            $room = $task->room;
            $timezone = $room?->hotel?->timezone ?? config('app.timezone');
            $arrivalToday = $room ? ($arrivalMap[$room->id] ?? false) : false;
            $priority = $this->priorityService->computePriorityForTask($task, $arrivalToday);

            return [
                'id' => $task->id,
                'type' => $task->type,
                'status' => $task->status,
                'priority' => $priority,
                'started_at' => $this->formatDateTimeLocal($task->started_at, $timezone),
                'created_at' => $this->formatDateTimeLocal($task->created_at, $timezone),
                'duration_seconds' => $task->duration_seconds,
                'outcome' => $task->outcome,
                'arrival_today' => $arrivalToday,
                'room' => $room ? [
                    'id' => $room->id,
                    'number' => $room->number,
                    'floor' => $room->floor,
                    'room_type' => $room->roomType?->name,
                ] : null,
                'participants' => $task->participants
                    ->map(fn ($participant): array => [
                        'id' => $participant->id,
                        'name' => $participant->name,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    private function currentCleaningTask(Room $room): ?HousekeepingTask
    {
        return $this->findTaskByTypes($room, [
            HousekeepingTask::TYPE_CLEANING,
            HousekeepingTask::TYPE_REDO_CLEANING,
        ]);
    }

    private function currentInspectionTask(Room $room): ?HousekeepingTask
    {
        return $this->findTaskByTypes($room, [
            HousekeepingTask::TYPE_INSPECTION,
            HousekeepingTask::TYPE_REDO_INSPECTION,
        ]);
    }

    /**
     * @param  array<int, string>  $types
     */
    private function findTaskByTypes(Room $room, array $types): ?HousekeepingTask
    {
        return HousekeepingTask::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('hotel_id', $room->hotel_id)
            ->where('room_id', $room->id)
            ->whereIn('type', $types)
            ->whereIn('status', [
                HousekeepingTask::STATUS_PENDING,
                HousekeepingTask::STATUS_IN_PROGRESS,
            ])
            ->orderByRaw("case status when 'in_progress' then 0 else 1 end")
            ->orderByDesc('created_at')
            ->first();
    }

    private function inspectionChecklistPayload(Room $room, HousekeepingTask $task): ?array
    {
        $checklist = $this->housekeepingService->resolveChecklist($room);

        if (! $checklist) {
            return null;
        }

        $responses = $task->checklistItems
            ->keyBy('checklist_item_id');

        $items = $checklist->items
            ->where('is_active', true)
            ->map(function ($item) use ($responses): array {
                $response = $responses->get($item->id);

                return [
                    'id' => $item->id,
                    'label' => $item->label,
                    'is_required' => $item->is_required,
                    'is_ok' => $response?->is_ok,
                    'note' => $response?->note,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $checklist->id,
            'name' => $checklist->name,
            'scope' => $checklist->scope,
            'items' => $items,
        ];
    }

    private function lastInspectionTask(Room $room): ?HousekeepingTask
    {
        return HousekeepingTask::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('hotel_id', $room->hotel_id)
            ->where('room_id', $room->id)
            ->whereIn('type', [
                HousekeepingTask::TYPE_INSPECTION,
                HousekeepingTask::TYPE_REDO_INSPECTION,
            ])
            ->where('status', HousekeepingTask::STATUS_DONE)
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->first();
    }

    private function inspectionSummary(Room $room): ?array
    {
        $lastInspection = $this->lastInspectionTask($room);

        if (! $lastInspection) {
            return null;
        }

        $remarks = null;

        if ($lastInspection->outcome === HousekeepingTask::OUTCOME_FAILED) {
            $lastInspection->loadMissing('checklistItems.checklistItem');
            $remarks = $lastInspection->checklistItems
                ->filter(fn ($item): bool => ! $item->is_ok && (string) $item->note !== '')
                ->map(fn ($item): array => [
                    'label' => $item->checklistItem?->label,
                    'note' => $item->note,
                ])
                ->values()
                ->all();
        }

        return [
            'outcome' => $lastInspection->outcome,
            'ended_at' => $this->formatDateTimeLocal($lastInspection->ended_at, $room),
            'remarks' => $remarks,
        ];
    }

    /**
     * @param  array<int, string>  $roomIds
     * @return array<string, bool>
     */
    private function arrivalMapForRooms(array $roomIds, string $tenantId, int $hotelId): array
    {
        if ($roomIds === []) {
            return [];
        }

        $timezone = Hotel::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $hotelId)
            ->value('timezone') ?? config('app.timezone');

        $today = Carbon::now($timezone)->toDateString();

        $arrivalRoomIds = Reservation::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereIn('room_id', $roomIds)
            ->whereDate('check_in_date', $today)
            ->whereIn('status', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_CONFIRMED,
            ])
            ->pluck('room_id')
            ->map(fn ($value) => (string) $value)
            ->all();

        return array_fill_keys($arrivalRoomIds, true);
    }

    private function formatDateTimeLocal(?Carbon $date, Room|string|null $room = null): ?string
    {
        if (! $date) {
            return null;
        }

        $timezone = $room instanceof Room
            ? ($room->hotel?->timezone ?? config('app.timezone'))
            : ($room ?? config('app.timezone'));

        return $date->copy()->setTimezone($timezone)->format('Y-m-d\\TH:i:s');
    }
}
