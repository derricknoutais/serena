<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HousekeepingPriorityService
{
    public function computePriorityForRoom(
        Room $room,
        ?bool $arrivalToday = null,
        ?Carbon $now = null
    ): string {
        if ($room->status === Room::STATUS_OUT_OF_ORDER) {
            return HousekeepingTask::PRIORITY_LOW;
        }

        $now = $now ?? $this->nowForRoom($room);
        $arrivalToday = $arrivalToday ?? $this->hasArrivalToday($room, $now);

        if ($room->hk_status === Room::HK_STATUS_REDO) {
            return HousekeepingTask::PRIORITY_URGENT;
        }

        if ($room->hk_status === Room::HK_STATUS_AWAITING_INSPECTION && $arrivalToday) {
            return HousekeepingTask::PRIORITY_URGENT;
        }

        if ($room->hk_status === Room::HK_STATUS_DIRTY && $arrivalToday) {
            return $this->shouldEscalateAfterNoon($now)
                ? HousekeepingTask::PRIORITY_URGENT
                : HousekeepingTask::PRIORITY_HIGH;
        }

        if ($room->hk_status === Room::HK_STATUS_DIRTY) {
            return HousekeepingTask::PRIORITY_NORMAL;
        }

        return HousekeepingTask::PRIORITY_LOW;
    }

    public function computePriorityForTask(
        HousekeepingTask $task,
        ?bool $arrivalToday = null,
        ?Carbon $now = null
    ): string {
        $room = $task->room;

        if (! $room) {
            return $task->priority ?? HousekeepingTask::PRIORITY_NORMAL;
        }

        if (
            $task->status === HousekeepingTask::STATUS_IN_PROGRESS
            && $task->type === HousekeepingTask::TYPE_CLEANING
        ) {
            return $task->priority;
        }

        return $this->computePriorityForRoom($room, $arrivalToday, $now);
    }

    public function syncTaskPriority(
        HousekeepingTask $task,
        ?User $user = null,
        ?bool $arrivalToday = null,
        ?Carbon $now = null
    ): void {
        $computed = $this->computePriorityForTask($task, $arrivalToday, $now);

        if ($computed === $task->priority) {
            return;
        }

        $from = $task->priority;
        $task->priority = $computed;
        $task->save();

        $activity = activity('housekeeping')->performedOn($task);

        if ($user) {
            $activity->causedBy($user);
        }

        $activity
            ->withProperties([
                'room_id' => $task->room_id,
                'task_id' => $task->id,
                'from_priority' => $from,
                'to_priority' => $computed,
            ])
            ->event('priority_updated')
            ->log('priority_updated');
    }

    public function syncRoomTasks(Room $room, ?User $user = null, ?Carbon $now = null): void
    {
        if ($room->status === Room::STATUS_OUT_OF_ORDER) {
            return;
        }

        $now = $now ?? $this->nowForRoom($room);
        $arrivalToday = $this->hasArrivalToday($room, $now);

        $tasks = HousekeepingTask::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('hotel_id', $room->hotel_id)
            ->where('room_id', $room->id)
            ->whereIn('status', [
                HousekeepingTask::STATUS_PENDING,
                HousekeepingTask::STATUS_IN_PROGRESS,
            ])
            ->get();

        foreach ($tasks as $task) {
            $task->setRelation('room', $room);
            $this->syncTaskPriority($task, $user, $arrivalToday, $now);
        }
    }

    public function syncHotelTasks(int|string $tenantId, int $hotelId, ?User $user = null): void
    {
        $tasks = HousekeepingTask::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereIn('status', [
                HousekeepingTask::STATUS_PENDING,
                HousekeepingTask::STATUS_IN_PROGRESS,
            ])
            ->with(['room.hotel'])
            ->get();

        if ($tasks->isEmpty()) {
            return;
        }

        $rooms = $tasks
            ->pluck('room')
            ->filter()
            ->unique('id');

        $arrivalMap = $this->arrivalMapForRooms($rooms);

        foreach ($tasks as $task) {
            $room = $task->room;
            if (! $room || $room->status === Room::STATUS_OUT_OF_ORDER) {
                continue;
            }

            $arrivalToday = $arrivalMap[$room->id] ?? false;
            $now = $this->nowForRoom($room);
            $this->syncTaskPriority($task, $user, $arrivalToday, $now);
        }
    }

    private function hasArrivalToday(Room $room, Carbon $now): bool
    {
        $today = $now->toDateString();

        return Reservation::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('hotel_id', $room->hotel_id)
            ->where('room_id', $room->id)
            ->whereDate('check_in_date', $today)
            ->whereIn('status', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_CONFIRMED,
            ])
            ->exists();
    }

    /**
     * @param  Collection<int, Room>  $rooms
     * @return array<string, bool>
     */
    private function arrivalMapForRooms(Collection $rooms): array
    {
        $room = $rooms->first();
        if (! $room) {
            return [];
        }

        $now = $this->nowForRoom($room);
        $today = $now->toDateString();
        $roomIds = $rooms->pluck('id')->all();

        $arrivalRoomIds = Reservation::query()
            ->where('tenant_id', $room->tenant_id)
            ->where('hotel_id', $room->hotel_id)
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

    private function nowForRoom(Room $room): Carbon
    {
        $room->loadMissing('hotel');

        $timezone = $room->hotel?->timezone ?? config('app.timezone');

        return Carbon::now($timezone);
    }

    private function shouldEscalateAfterNoon(Carbon $now): bool
    {
        return $now->hour >= 12;
    }
}
