<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HousekeepingChecklist;
use App\Models\HousekeepingChecklistItem;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskChecklistItem;
use App\Models\Room;
use App\Models\User;
use App\Notifications\GenericPushNotification;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HousekeepingService
{
    public function __construct(
        private readonly HousekeepingPriorityService $priorityService,
        private readonly NotificationRecipientResolver $recipientResolver,
        private readonly PushNotificationSender $pushNotificationSender,
    ) {}

    public function createTaskAfterCheckout(Room $room, ?User $user = null): ?HousekeepingTask
    {
        return $this->createCleaningTask($room, HousekeepingTask::SOURCE_CHECKOUT, $user);
    }

    public function createManualCleaningTask(Room $room, ?User $user = null): ?HousekeepingTask
    {
        return $this->createCleaningTask($room, HousekeepingTask::SOURCE_RECEPTION, $user);
    }

    public function startTask(HousekeepingTask $task, User $user): HousekeepingTask
    {
        if ($task->status === HousekeepingTask::STATUS_DONE) {
            throw ValidationException::withMessages([
                'task' => 'Cette tâche est déjà terminée.',
            ]);
        }

        if ($task->status === HousekeepingTask::STATUS_IN_PROGRESS) {
            return $this->joinTask($task, $user);
        }

        if ($task->status !== HousekeepingTask::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'task' => 'Cette tâche ne peut pas être démarrée.',
            ]);
        }

        $task->status = HousekeepingTask::STATUS_IN_PROGRESS;
        $task->started_at = $task->started_at ?? now();
        $task->save();

        if (in_array($task->type, [
            HousekeepingTask::TYPE_CLEANING,
            HousekeepingTask::TYPE_REDO_CLEANING,
        ], true) && $task->room) {
            $this->transitionRoomStatus($task->room, Room::HK_STATUS_CLEANING, $user);
        }

        $this->attachParticipant($task, $user);

        activity('housekeeping')
            ->performedOn($task)
            ->causedBy($user)
            ->withProperties([
                'room_id' => $task->room_id,
                'task_id' => $task->id,
                'status' => $task->status,
            ])
            ->event('started')
            ->log('started');

        return $task;
    }

    public function joinTask(HousekeepingTask $task, User $user): HousekeepingTask
    {
        if ($task->status !== HousekeepingTask::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages([
                'task' => 'Cette tâche doit être en cours pour être rejointe.',
            ]);
        }

        $joined = $this->attachParticipant($task, $user);

        if ($joined) {
            activity('housekeeping')
                ->performedOn($task)
                ->causedBy($user)
                ->withProperties([
                    'room_id' => $task->room_id,
                    'task_id' => $task->id,
                    'status' => $task->status,
                ])
                ->event('joined')
                ->log('joined');
        }

        return $task;
    }

    public function finishCleaning(HousekeepingTask $task, User $user): HousekeepingTask
    {
        if (! in_array($task->type, [
            HousekeepingTask::TYPE_CLEANING,
            HousekeepingTask::TYPE_REDO_CLEANING,
        ], true)) {
            throw ValidationException::withMessages([
                'task' => 'Cette tâche de ménage est invalide.',
            ]);
        }

        if ($task->status !== HousekeepingTask::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages([
                'task' => 'Cette tâche doit être en cours pour être terminée.',
            ]);
        }

        $this->attachParticipant($task, $user);

        $task->status = HousekeepingTask::STATUS_DONE;
        $task->ended_at = now();
        $task->started_at = $task->started_at ?? $task->ended_at;
        $task->duration_seconds = $task->started_at->diffInSeconds($task->ended_at);
        $task->save();

        $room = $task->room()->first();
        if ($room) {
            $isRedo = $task->type === HousekeepingTask::TYPE_REDO_CLEANING;

            if ($this->isInspectionEnabled($room)) {
                $this->transitionRoomStatus($room, Room::HK_STATUS_AWAITING_INSPECTION, $user);

                if ($isRedo) {
                    $this->createRedoInspectionTask($room, $user);
                } else {
                    $this->createInspectionTask($room, $user);
                }
            } else {
                $this->transitionRoomStatus($room, Room::HK_STATUS_INSPECTED, $user);
            }
        }

        $this->closeParticipants($task);

        activity('housekeeping')
            ->performedOn($task)
            ->causedBy($user)
            ->withProperties([
                'room_id' => $task->room_id,
                'task_id' => $task->id,
                'status' => $task->status,
            ])
            ->event('cleaning_finished')
            ->log('cleaning_finished');

        return $task;
    }

    public function startInspection(HousekeepingTask $task, User $user): HousekeepingTask
    {
        if (! in_array($task->type, [
            HousekeepingTask::TYPE_INSPECTION,
            HousekeepingTask::TYPE_REDO_INSPECTION,
        ], true)) {
            throw ValidationException::withMessages([
                'task' => 'Cette inspection est invalide.',
            ]);
        }

        if ($task->room && $task->room->hk_status !== Room::HK_STATUS_AWAITING_INSPECTION) {
            throw ValidationException::withMessages([
                'task' => "Cette chambre n'est pas en attente d'inspection.",
            ]);
        }

        return $this->startTask($task, $user);
    }

    /**
     * @param  array<int, array{checklist_item_id:int,is_ok:bool,note:?string}>  $payload
     */
    public function finishInspection(HousekeepingTask $task, User $user, array $payload): HousekeepingTask
    {
        if (! in_array($task->type, [
            HousekeepingTask::TYPE_INSPECTION,
            HousekeepingTask::TYPE_REDO_INSPECTION,
        ], true)) {
            throw ValidationException::withMessages([
                'task' => 'Cette inspection est invalide.',
            ]);
        }

        if ($task->status !== HousekeepingTask::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages([
                'task' => 'Cette inspection doit être en cours pour être terminée.',
            ]);
        }

        $this->attachParticipant($task, $user);

        $room = $task->room()->first();
        if (! $room) {
            throw ValidationException::withMessages([
                'task' => 'La chambre associée est introuvable.',
            ]);
        }

        $checklist = $this->resolveChecklist($room);
        $activeItems = $checklist?->items->where('is_active', true) ?? collect();
        $responses = $this->syncInspectionResponses($task, $activeItems, $payload);

        $hasFailure = $responses->contains(fn (HousekeepingTaskChecklistItem $item): bool => ! $item->is_ok);

        $task->status = HousekeepingTask::STATUS_DONE;
        $task->ended_at = now();
        $task->started_at = $task->started_at ?? $task->ended_at;
        $task->duration_seconds = $task->started_at->diffInSeconds($task->ended_at);
        $task->outcome = $hasFailure
            ? HousekeepingTask::OUTCOME_FAILED
            : HousekeepingTask::OUTCOME_PASSED;
        $task->save();

        $occupied = $room->isOccupiedNow();
        $inspectionProperties = ['occupied_at_validation' => $occupied];

        if ($hasFailure) {
            $remarks = $this->buildInspectionRemarks($responses);
            $this->transitionRoomStatus($room, Room::HK_STATUS_REDO, $user, $remarks, false, $inspectionProperties);
            $this->createRedoCleaningTaskFromInspection($room, $user, $remarks);
        } else {
            $targetStatus = $occupied ? Room::HK_STATUS_IN_USE : Room::HK_STATUS_INSPECTED;
            $this->transitionRoomStatus($room, $targetStatus, $user, null, false, $inspectionProperties);
        }

        $this->sendInspectionOutcomePush($room, $user, ! $hasFailure, $occupied);

        $this->closeParticipants($task);

        activity('housekeeping')
            ->performedOn($task)
            ->causedBy($user)
            ->withProperties([
                'room_id' => $task->room_id,
                'task_id' => $task->id,
                'status' => $task->status,
                'outcome' => $task->outcome,
            ])
            ->event($hasFailure ? 'inspection_failed' : 'inspection_passed')
            ->log($hasFailure ? 'inspection_failed' : 'inspection_passed');

        return $task;
    }

    private function attachParticipant(HousekeepingTask $task, User $user): bool
    {
        $participant = $task->participants()
            ->whereKey($user->id)
            ->first();

        if ($participant) {
            if ($participant->pivot->left_at !== null) {
                $task->participants()->updateExistingPivot($user->id, [
                    'joined_at' => now(),
                    'left_at' => null,
                ]);

                return true;
            }

            return false;
        }

        $task->participants()->attach($user->id, [
            'joined_at' => now(),
        ]);

        return true;
    }

    private function closeParticipants(HousekeepingTask $task): void
    {
        $task->participants()
            ->newPivotStatement()
            ->where('task_id', $task->id)
            ->whereNull('left_at')
            ->update([
                'left_at' => now(),
            ]);
    }

    private function createInspectionTask(Room $room, ?User $user = null): ?HousekeepingTask
    {
        if ($room->status === Room::STATUS_OUT_OF_ORDER) {
            return null;
        }

        $existing = HousekeepingTask::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('hotel_id', $room->hotel_id)
            ->where('room_id', $room->id)
            ->where('type', HousekeepingTask::TYPE_INSPECTION)
            ->whereIn('status', [
                HousekeepingTask::STATUS_PENDING,
                HousekeepingTask::STATUS_IN_PROGRESS,
            ])
            ->orderByDesc('created_at')
            ->first();

        if ($existing) {
            $this->priorityService->syncTaskPriority($existing, $user);

            return $existing;
        }

        $task = HousekeepingTask::query()->create([
            'tenant_id' => $room->tenant_id,
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'type' => HousekeepingTask::TYPE_INSPECTION,
            'status' => HousekeepingTask::STATUS_PENDING,
            'priority' => $this->priorityService->computePriorityForRoom($room),
            'created_from' => HousekeepingTask::SOURCE_CHECKOUT,
        ]);

        $activity = activity('housekeeping')->performedOn($task);
        if ($user) {
            $activity->causedBy($user);
        }

        $activity
            ->withProperties([
                'room_id' => $room->id,
                'task_id' => $task->id,
                'priority' => $task->priority,
                'type' => $task->type,
                'created_from' => $task->created_from,
            ])
            ->event('task_created')
            ->log('task_created');

        return $task;
    }

    private function createRedoInspectionTask(Room $room, ?User $user = null): ?HousekeepingTask
    {
        if ($room->status === Room::STATUS_OUT_OF_ORDER) {
            return null;
        }

        $existing = HousekeepingTask::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('hotel_id', $room->hotel_id)
            ->where('room_id', $room->id)
            ->where('type', HousekeepingTask::TYPE_REDO_INSPECTION)
            ->whereIn('status', [
                HousekeepingTask::STATUS_PENDING,
                HousekeepingTask::STATUS_IN_PROGRESS,
            ])
            ->orderByDesc('created_at')
            ->first();

        if ($existing) {
            $this->priorityService->syncTaskPriority($existing, $user);

            return $existing;
        }

        $task = HousekeepingTask::query()->create([
            'tenant_id' => $room->tenant_id,
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'type' => HousekeepingTask::TYPE_REDO_INSPECTION,
            'status' => HousekeepingTask::STATUS_PENDING,
            'priority' => $this->priorityService->computePriorityForRoom($room),
            'created_from' => HousekeepingTask::SOURCE_RECEPTION,
        ]);

        $activity = activity('housekeeping')->performedOn($task);
        if ($user) {
            $activity->causedBy($user);
        }

        $activity
            ->withProperties([
                'room_id' => $room->id,
                'task_id' => $task->id,
                'priority' => $task->priority,
                'type' => $task->type,
                'created_from' => $task->created_from,
            ])
            ->event('task_created')
            ->log('task_created');

        return $task;
    }

    private function createRedoCleaningTaskFromInspection(
        Room $room,
        ?User $user = null,
        ?string $remarks = null
    ): ?HousekeepingTask {
        if ($room->status === Room::STATUS_OUT_OF_ORDER) {
            return null;
        }

        $existing = HousekeepingTask::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('hotel_id', $room->hotel_id)
            ->where('room_id', $room->id)
            ->where('type', HousekeepingTask::TYPE_REDO_CLEANING)
            ->whereIn('status', [
                HousekeepingTask::STATUS_PENDING,
                HousekeepingTask::STATUS_IN_PROGRESS,
            ])
            ->orderByDesc('created_at')
            ->first();

        if ($existing) {
            $this->priorityService->syncTaskPriority($existing, $user);

            return $existing;
        }

        $task = HousekeepingTask::query()->create([
            'tenant_id' => $room->tenant_id,
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'type' => HousekeepingTask::TYPE_REDO_CLEANING,
            'status' => HousekeepingTask::STATUS_PENDING,
            'priority' => $this->priorityService->computePriorityForRoom($room),
            'created_from' => HousekeepingTask::SOURCE_RECEPTION,
        ]);

        $activity = activity('housekeeping')->performedOn($task);
        if ($user) {
            $activity->causedBy($user);
        }

        $properties = [
            'room_id' => $room->id,
            'task_id' => $task->id,
            'priority' => $task->priority,
            'type' => $task->type,
            'created_from' => $task->created_from,
        ];

        if ($remarks) {
            $properties['remarks'] = $remarks;
        }

        $activity
            ->withProperties($properties)
            ->event('task_created')
            ->log('task_created');

        return $task;
    }

    public function resolveChecklist(Room $room): ?HousekeepingChecklist
    {
        $room->loadMissing('roomType');

        if ($room->room_type_id) {
            $roomTypeChecklist = HousekeepingChecklist::query()
                ->where('tenant_id', $room->tenant_id)
                ->where('scope', HousekeepingChecklist::SCOPE_ROOM_TYPE)
                ->where('room_type_id', $room->room_type_id)
                ->where('is_active', true)
                ->where(function ($query) use ($room): void {
                    $query->where('hotel_id', $room->hotel_id)->orWhereNull('hotel_id');
                })
                ->orderByRaw('hotel_id is null')
                ->with('items')
                ->first();

            if ($roomTypeChecklist) {
                return $roomTypeChecklist;
            }
        }

        return HousekeepingChecklist::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('scope', HousekeepingChecklist::SCOPE_GLOBAL)
            ->where('is_active', true)
            ->where(function ($query) use ($room): void {
                $query->where('hotel_id', $room->hotel_id)->orWhereNull('hotel_id');
            })
            ->orderByRaw('hotel_id is null')
            ->with('items')
            ->first();
    }

    /**
     * @param  Collection<int, HousekeepingChecklistItem>  $activeItems
     * @param  array<int, array{checklist_item_id:int,is_ok:bool,note:?string}>  $payload
     * @return Collection<int, HousekeepingTaskChecklistItem>
     */
    private function syncInspectionResponses(
        HousekeepingTask $task,
        Collection $activeItems,
        array $payload
    ): Collection {
        $payloadByItem = collect($payload)->keyBy('checklist_item_id');

        $responses = collect();

        foreach ($activeItems as $item) {
            $entry = $payloadByItem->get($item->id);
            $isOk = $entry['is_ok'] ?? true;
            $note = $entry['note'] ?? null;

            $response = HousekeepingTaskChecklistItem::query()->updateOrCreate([
                'task_id' => $task->id,
                'checklist_item_id' => $item->id,
            ], [
                'is_ok' => $isOk,
                'note' => $note,
            ]);

            $responses->push($response);
        }

        return $responses;
    }

    public function markRoomDirtyAfterCheckout(Room $room, ?User $user = null): void
    {
        $this->transitionRoomStatus($room, Room::HK_STATUS_DIRTY, $user, null, true);
    }

    public function updateRoomStatus(Room $room, string $toStatus, User $user, ?string $remarks = null): void
    {
        $this->transitionRoomStatus($room, $toStatus, $user, $remarks);
    }

    public function forceRoomStatus(Room $room, string $toStatus, ?User $user = null, ?string $remarks = null): void
    {
        $this->transitionRoomStatus($room, $toStatus, $user, $remarks, true);
    }

    private function isInspectionEnabled(Room $room): bool
    {
        $room->loadMissing('hotel');

        $settings = $room->hotel?->stay_settings ?? [];
        $configured = data_get($settings, 'housekeeping.inspection_enabled');

        if ($configured !== null) {
            return (bool) $configured;
        }

        return true;
    }

    /**
     * @param  Collection<int, HousekeepingTaskChecklistItem>  $responses
     */
    private function buildInspectionRemarks(Collection $responses): ?string
    {
        $remarks = $responses
            ->filter(fn (HousekeepingTaskChecklistItem $item): bool => ! $item->is_ok && (string) $item->note !== '')
            ->map(fn (HousekeepingTaskChecklistItem $item): string => (string) $item->note)
            ->values();

        if ($remarks->isEmpty()) {
            return null;
        }

        return $remarks->implode(' | ');
    }

    private function transitionRoomStatus(
        Room $room,
        string $toStatus,
        ?User $user = null,
        ?string $remarks = null,
        bool $force = false,
        array $extraProperties = [],
    ): void {
        $fromStatus = $room->hk_status;

        if ($fromStatus === $toStatus) {
            return;
        }

        if (! $force && ! $this->isAllowedRoomTransition($fromStatus, $toStatus)) {
            throw ValidationException::withMessages([
                'hk_status' => 'Transition de statut ménage non autorisée.',
            ]);
        }

        $room->hk_status = $toStatus;
        $room->save();

        $activity = activity('room')->performedOn($room);
        if ($user) {
            $activity->causedBy($user);
        }

        $properties = array_merge([
            'from_hk_status' => $fromStatus,
            'to_hk_status' => $toStatus,
            'room_number' => $room->number,
        ], $extraProperties);

        if ($remarks) {
            $properties['remarks'] = $remarks;
        }

        $activity
            ->withProperties($properties)
            ->event('hk_updated')
            ->log('hk_updated');

        if ($toStatus === Room::HK_STATUS_DIRTY) {
            $this->sendRoomDirtyPush($room, $user);
        }

        $this->priorityService->syncRoomTasks($room, $user);
    }

    private function createCleaningTask(Room $room, string $createdFrom, ?User $user = null): ?HousekeepingTask
    {
        if ($room->status === Room::STATUS_OUT_OF_ORDER) {
            return null;
        }

        $existing = HousekeepingTask::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('hotel_id', $room->hotel_id)
            ->where('room_id', $room->id)
            ->where('type', HousekeepingTask::TYPE_CLEANING)
            ->whereIn('status', [
                HousekeepingTask::STATUS_PENDING,
                HousekeepingTask::STATUS_IN_PROGRESS,
            ])
            ->orderByDesc('created_at')
            ->first();

        if ($existing) {
            $this->priorityService->syncTaskPriority($existing, $user);

            return $existing;
        }

        $priority = $this->priorityService->computePriorityForRoom($room);

        $task = HousekeepingTask::query()->create([
            'tenant_id' => $room->tenant_id,
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'type' => HousekeepingTask::TYPE_CLEANING,
            'status' => HousekeepingTask::STATUS_PENDING,
            'priority' => $priority,
            'created_from' => $createdFrom,
        ]);

        $activity = activity('housekeeping')->performedOn($task);
        if ($user) {
            $activity->causedBy($user);
        }

        $activity
            ->withProperties([
                'room_id' => $room->id,
                'task_id' => $task->id,
                'priority' => $task->priority,
                'type' => $task->type,
                'created_from' => $task->created_from,
            ])
            ->event('task_created')
            ->log('task_created');

        return $task;
    }

    private function sendRoomDirtyPush(Room $room, ?User $user): void
    {
        $userIds = $this->resolvePushRecipientIds($room);

        if ($userIds === []) {
            return;
        }

        $status = Room::HK_STATUS_DIRTY;
        $title = 'Chambre sale';
        $body = $this->buildRoomStatusBody($room, $status, $user);
        $url = sprintf('/housekeeping?room=%s', $room->id);

        $notification = new GenericPushNotification(
            title: $title,
            body: $body,
            url: $url,
            icon: null,
            badge: null,
            tag: 'hk-dirty',
            tenantId: (string) $room->tenant_id,
            hotelId: $room->hotel_id,
        );

        $this->pushNotificationSender->send(
            tenantId: (string) $room->tenant_id,
            notification: $notification,
            userIds: $userIds,
        );
    }

    private function sendInspectionOutcomePush(Room $room, User $user, bool $approved, bool $occupied): void
    {
        $userIds = $this->resolvePushRecipientIds($room);

        if ($userIds === []) {
            return;
        }

        $status = $approved
            ? ($occupied ? Room::HK_STATUS_IN_USE : Room::HK_STATUS_INSPECTED)
            : Room::HK_STATUS_REDO;
        $title = $approved ? 'Inspection approuvée' : 'Inspection refusée';
        $body = $this->buildRoomStatusBody($room, $status, $user);
        $url = sprintf('/housekeeping?room=%s', $room->id);
        $tag = $approved ? 'hk-inspection-approved' : 'hk-inspection-refused';

        $notification = new GenericPushNotification(
            title: $title,
            body: $body,
            url: $url,
            icon: null,
            badge: null,
            tag: $tag,
            tenantId: (string) $room->tenant_id,
            hotelId: $room->hotel_id,
        );

        $this->pushNotificationSender->send(
            tenantId: (string) $room->tenant_id,
            notification: $notification,
            userIds: $userIds,
        );
    }

    /**
     * @return array<int, int>
     */
    private function resolvePushRecipientIds(Room $room): array
    {
        return $this->recipientResolver
            ->resolve('room.hk_status_updated', (string) $room->tenant_id, (int) $room->hotel_id)
            ->pluck('id')
            ->all();
    }

    private function buildRoomStatusBody(Room $room, string $status, ?User $user): string
    {
        $statusLabel = $this->hkStatusLabel($status);
        $causerName = $user?->name ?? 'Système';

        return sprintf(
            'Chambre %s — %s (%s). Par %s.',
            $room->number,
            $statusLabel,
            $status,
            $causerName,
        );
    }

    private function hkStatusLabel(string $status): string
    {
        return match ($status) {
            Room::HK_STATUS_DIRTY => 'Sale',
            Room::HK_STATUS_CLEANING => 'En cours',
            Room::HK_STATUS_AWAITING_INSPECTION => 'En attente d’inspection',
            Room::HK_STATUS_REDO => 'À refaire',
            Room::HK_STATUS_INSPECTED => 'Inspectée',
            Room::HK_STATUS_IN_USE => 'En usage',
            default => 'Propre',
        };
    }

    private function isAllowedRoomTransition(?string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === null || $fromStatus === '') {
            return true;
        }

        $map = [
            Room::HK_STATUS_DIRTY => [
                Room::HK_STATUS_CLEANING,
            ],
            Room::HK_STATUS_CLEANING => [
                Room::HK_STATUS_AWAITING_INSPECTION,
            ],
            Room::HK_STATUS_AWAITING_INSPECTION => [
                Room::HK_STATUS_INSPECTED,
                Room::HK_STATUS_REDO,
                Room::HK_STATUS_IN_USE,
            ],
            Room::HK_STATUS_REDO => [
                Room::HK_STATUS_CLEANING,
            ],
            Room::HK_STATUS_INSPECTED => [
                Room::HK_STATUS_IN_USE,
            ],
            Room::HK_STATUS_IN_USE => [
                Room::HK_STATUS_DIRTY,
            ],
        ];

        return in_array($toStatus, $map[$fromStatus] ?? [], true);
    }
}
