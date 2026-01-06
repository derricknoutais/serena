<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\HousekeepingTask;
use App\Models\Room;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('renders housekeeping reports for managers', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-01-04 10:00:00', 'Africa/Douala'));

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('hk-reports');

    $user->assignRole('manager');

    $room->update([
        'hk_status' => Room::HK_STATUS_AWAITING_INSPECTION,
    ]);

    HousekeepingTask::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'type' => HousekeepingTask::TYPE_CLEANING,
        'status' => HousekeepingTask::STATUS_DONE,
        'priority' => HousekeepingTask::PRIORITY_NORMAL,
        'created_from' => HousekeepingTask::SOURCE_CHECKOUT,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'duration_seconds' => 3600,
    ]);

    HousekeepingTask::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'type' => HousekeepingTask::TYPE_INSPECTION,
        'status' => HousekeepingTask::STATUS_DONE,
        'priority' => HousekeepingTask::PRIORITY_NORMAL,
        'created_from' => HousekeepingTask::SOURCE_CHECKOUT,
        'ended_at' => now(),
        'outcome' => HousekeepingTask::OUTCOME_PASSED,
    ]);

    HousekeepingTask::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'type' => HousekeepingTask::TYPE_INSPECTION,
        'status' => HousekeepingTask::STATUS_DONE,
        'priority' => HousekeepingTask::PRIORITY_NORMAL,
        'created_from' => HousekeepingTask::SOURCE_CHECKOUT,
        'ended_at' => now(),
        'outcome' => HousekeepingTask::OUTCOME_FAILED,
    ]);

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/housekeeping/reports',
        tenantDomain($tenant),
    ));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Housekeeping/Reports')
            ->where('summary.rooms_cleaned', 1)
            ->where('summary.rooms_inspected', 1)
            ->where('summary.rooms_redone', 1)
            ->where('summary.avg_cleaning_seconds', 3600)
        );

    Carbon::setTestNow();
});
