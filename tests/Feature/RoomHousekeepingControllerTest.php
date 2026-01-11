<?php

use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

test('updates housekeeping status and notifies recipients', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    Notification::fake();

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Housekeeping Hotel',
        'slug' => 'housekeeping-hotel',
        'plan' => 'standard',
    ]);
    $tenant->createDomain(['domain' => 'housekeeping.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Housekeeping Main',
        'code' => 'HK1',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $roomType = RoomType::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Standard',
        'code' => 'STD',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 10000,
    ]);

    $room = Room::query()->create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '101',
        'floor' => '1',
        'status' => Room::STATUS_AVAILABLE,
        'hk_status' => Room::HK_STATUS_DIRTY,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'active_hotel_id' => $hotel->id,
    ]);
    $user->assignRole('owner');

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/frontdesk/rooms/%s/hk-status',
        $tenant->domains()->value('domain'),
        $room->id,
    ), [
        'hk_status' => 'cleaning',
    ]);

    $response->assertOk();
    expect($room->refresh()->hk_status)->toBe(Room::HK_STATUS_CLEANING);

    Notification::assertSentTo($user, \App\Notifications\AppNotification::class);
});

test('creates a cleaning task when marking a room dirty from frontdesk', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    Notification::fake();

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Housekeeping Hotel',
        'slug' => 'housekeeping-hotel',
        'plan' => 'standard',
    ]);
    $tenant->createDomain(['domain' => 'housekeeping.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Housekeeping Main',
        'code' => 'HK1',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $roomType = RoomType::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Standard',
        'code' => 'STD',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 10000,
    ]);

    $room = Room::query()->create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '101',
        'floor' => '1',
        'status' => Room::STATUS_AVAILABLE,
        'hk_status' => Room::HK_STATUS_IN_USE,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'active_hotel_id' => $hotel->id,
    ]);
    $user->assignRole('owner');

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/frontdesk/rooms/%s/hk-status',
        $tenant->domains()->value('domain'),
        $room->id,
    ), [
        'hk_status' => 'dirty',
    ]);

    $response->assertOk();
    expect($room->refresh()->hk_status)->toBe(Room::HK_STATUS_DIRTY);

    $task = HousekeepingTask::query()->first();

    expect($task)
        ->not->toBeNull()
        ->and($task->room_id)->toBe($room->id)
        ->and($task->status)->toBe(HousekeepingTask::STATUS_PENDING)
        ->and($task->priority)->toBe(HousekeepingTask::PRIORITY_NORMAL)
        ->and($task->created_from)->toBe(HousekeepingTask::SOURCE_RECEPTION);
});
