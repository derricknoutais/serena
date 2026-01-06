<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\ReservationStateMachine;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

it('creates a housekeeping task after checkout', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    Carbon::setTestNow('2026-01-04 10:00:00');

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('hk-checkout');

    $user->assignRole('owner');

    $reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => now()->copy()->subDay()->startOfDay(),
        'check_out_date' => now()->copy()->startOfDay(),
        'actual_check_in_at' => now()->copy()->subDay(),
    ]);

    $room->update([
        'status' => Room::STATUS_OCCUPIED,
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    Reservation::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'guest_id' => $reservation->guest_id,
        'room_type_id' => $reservation->room_type_id,
        'room_id' => $room->id,
        'offer_id' => null,
        'code' => 'RSV-NEXT',
        'status' => Reservation::STATUS_CONFIRMED,
        'source' => 'direct',
        'offer_name' => null,
        'offer_kind' => 'night',
        'adults' => 1,
        'children' => 0,
        'check_in_date' => now()->copy()->startOfDay(),
        'check_out_date' => now()->copy()->addDay()->startOfDay(),
        'expected_arrival_time' => '14:00',
        'actual_check_in_at' => null,
        'actual_check_out_at' => null,
        'currency' => $hotel->currency,
        'unit_price' => 10000,
        'base_amount' => 10000,
        'tax_amount' => 0,
        'total_amount' => 10000,
    ]);

    $stateMachine = app(ReservationStateMachine::class);

    $this->actingAs($user);

    $stateMachine->checkOut($reservation->fresh(), now());

    expect(HousekeepingTask::query()->count())->toBe(1);

    $task = HousekeepingTask::query()->first();

    expect($task)
        ->not->toBeNull()
        ->and($task->room_id)->toBe($room->id)
        ->and($task->status)->toBe(HousekeepingTask::STATUS_PENDING)
        ->and($task->priority)->toBe(HousekeepingTask::PRIORITY_HIGH)
        ->and($task->created_from)->toBe(HousekeepingTask::SOURCE_CHECKOUT);

    expect($room->fresh()->hk_status)->toBe('dirty');

    Carbon::setTestNow();
});

it('starts joins and finishes housekeeping tasks from the housekeeping flow', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('hk-flow');

    $user->assignRole('housekeeping');

    $room->update([
        'hk_status' => 'dirty',
    ]);

    $task = HousekeepingTask::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'type' => HousekeepingTask::TYPE_CLEANING,
        'status' => HousekeepingTask::STATUS_PENDING,
        'priority' => HousekeepingTask::PRIORITY_NORMAL,
        'created_from' => HousekeepingTask::SOURCE_CHECKOUT,
    ]);

    $domain = tenantDomain($tenant);

    $response = $this->actingAs($user)->post(sprintf(
        'http://%s/hk/rooms/%s/tasks/start',
        $domain,
        $room->id,
    ));

    $response->assertOk();
    expect($task->fresh()->status)->toBe(HousekeepingTask::STATUS_IN_PROGRESS);
    expect($task->participants()->count())->toBe(1);

    $secondUser = User::factory()->create([
        'tenant_id' => $tenant->id,
        'active_hotel_id' => $hotel->id,
        'email_verified_at' => now(),
    ]);
    $secondUser->assignRole('housekeeping');

    $joinResponse = $this->actingAs($secondUser)->post(sprintf(
        'http://%s/hk/rooms/%s/tasks/join',
        $domain,
        $room->id,
    ));

    $joinResponse->assertOk();
    expect($task->fresh()->participants()->count())->toBe(2);

    $finishResponse = $this->actingAs($secondUser)->post(sprintf(
        'http://%s/hk/rooms/%s/tasks/finish',
        $domain,
        $room->id,
    ));

    $finishResponse->assertOk();
    expect($task->fresh()->status)->toBe(HousekeepingTask::STATUS_DONE);
    expect($task->fresh()->ended_at)->not->toBeNull();
    expect($room->fresh()->hk_status)->toBe(Room::HK_STATUS_AWAITING_INSPECTION);
});
