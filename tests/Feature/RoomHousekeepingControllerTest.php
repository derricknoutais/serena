<?php

use App\Models\Hotel;
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
        'hk_status' => 'clean',
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
    expect($room->refresh()->hk_status)->toBe('dirty');

    Notification::assertSentTo($user, \App\Notifications\AppNotification::class);
});
