<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Services\HousekeepingPriorityService;
use Carbon\Carbon;

it('marks redo rooms as urgent priority', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-04 10:00:00', 'Africa/Douala'));

    ['room' => $room] = setupReservationEnvironment('hk-priority-redo');

    $room->update([
        'hk_status' => Room::HK_STATUS_REDO,
    ]);

    $service = app(HousekeepingPriorityService::class);

    expect($service->computePriorityForRoom($room))
        ->toBe(HousekeepingTask::PRIORITY_URGENT);

    Carbon::setTestNow();
});

it('marks dirty rooms with arrival today as high before noon', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-04 10:00:00', 'Africa/Douala'));

    ['room' => $room] = setupReservationEnvironment('hk-priority-high');

    $room->update([
        'hk_status' => Room::HK_STATUS_DIRTY,
    ]);

    $service = app(HousekeepingPriorityService::class);

    expect($service->computePriorityForRoom($room))
        ->toBe(HousekeepingTask::PRIORITY_HIGH);

    Carbon::setTestNow();
});

it('escalates dirty rooms with arrival today after noon', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-04 13:00:00', 'Africa/Douala'));

    ['room' => $room] = setupReservationEnvironment('hk-priority-urgent');

    $room->update([
        'hk_status' => Room::HK_STATUS_DIRTY,
    ]);

    $service = app(HousekeepingPriorityService::class);

    expect($service->computePriorityForRoom($room))
        ->toBe(HousekeepingTask::PRIORITY_URGENT);

    Carbon::setTestNow();
});

it('keeps priority for redo cleaning in progress', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'room' => $room,
    ] = setupReservationEnvironment('hk-priority-redo-cleaning');

    $task = HousekeepingTask::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'type' => HousekeepingTask::TYPE_REDO_CLEANING,
        'status' => HousekeepingTask::STATUS_IN_PROGRESS,
        'priority' => HousekeepingTask::PRIORITY_HIGH,
        'created_from' => HousekeepingTask::SOURCE_MANUAL,
        'started_at' => now(),
    ]);

    $service = app(HousekeepingPriorityService::class);

    expect($service->computePriorityForTask($task))
        ->toBe(HousekeepingTask::PRIORITY_HIGH);
});
