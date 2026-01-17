<?php

use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @return array{
 *   tenant: Tenant,
 *   hotel: Hotel,
 *   user: User,
 *   roomType: RoomType,
 *   oldRoom: Room,
 *   newRoom: Room,
 *   reservation: Reservation,
 *   domain: string
 * }
 */
function makeRoomMoveSetup(string $domain, array $overrides = []): array
{
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Move Hotel',
        'slug' => 'move-hotel',
        'plan' => 'standard',
        'contact_email' => 'move@hotel.test',
        'data' => [
            'name' => 'Move Hotel',
            'slug' => 'move-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => $domain]);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Move Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $roomType = RoomType::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Standard',
        'capacity_adults' => 2,
        'capacity_children' => 0,
        'base_price' => 15000,
    ]);

    $oldRoom = Room::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'room_type_id' => $roomType->getKey(),
        'number' => '101',
        'status' => Room::STATUS_IN_USE,
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $newRoom = Room::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'room_type_id' => $roomType->getKey(),
        'number' => '102',
        'status' => Room::STATUS_AVAILABLE,
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $guest = Guest::query()->create([
        'tenant_id' => $tenant->getKey(),
        'first_name' => 'Jean',
        'last_name' => 'Doe',
        'email' => 'guest@hotel.test',
        'phone' => '+237600000001',
    ]);

    $checkIn = Carbon::now()->startOfDay();
    $checkOut = (clone $checkIn)->addDays(2);

    $reservation = Reservation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'guest_id' => $guest->getKey(),
        'room_type_id' => $roomType->getKey(),
        'room_id' => $oldRoom->getKey(),
        'code' => 'RES-ROOM-MOVE',
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'actual_check_in_at' => Carbon::now(),
        'currency' => 'XAF',
        'unit_price' => 15000,
        'base_amount' => 30000,
        'tax_amount' => 0,
        'total_amount' => 30000,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('reservations.change_room');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    return array_merge([
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
        'roomType' => $roomType,
        'oldRoom' => $oldRoom,
        'newRoom' => $newRoom,
        'reservation' => $reservation,
        'domain' => $domain,
    ], $overrides);
}

beforeEach(function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

it('moves room and keeps hk status when not used', function (): void {
    $setup = makeRoomMoveSetup('move-not-used.serena.test');

    $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $setup['newRoom']->id,
                'vacated_usage' => 'not_used',
            ],
        )
        ->assertOk();

    $setup['oldRoom']->refresh();
    $setup['newRoom']->refresh();

    expect($setup['oldRoom']->status)->toBe(Room::STATUS_AVAILABLE)
        ->and($setup['oldRoom']->hk_status)->toBe(Room::HK_STATUS_INSPECTED)
        ->and($setup['newRoom']->status)->toBe(Room::STATUS_IN_USE);
});

it('moves room and marks old room dirty when used', function (): void {
    $setup = makeRoomMoveSetup('move-used.serena.test');

    $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $setup['newRoom']->id,
                'vacated_usage' => 'used',
            ],
        )
        ->assertOk();

    $setup['oldRoom']->refresh();
    $setup['newRoom']->refresh();

    expect($setup['oldRoom']->status)->toBe(Room::STATUS_AVAILABLE)
        ->and($setup['oldRoom']->hk_status)->toBe(Room::HK_STATUS_DIRTY)
        ->and($setup['newRoom']->status)->toBe(Room::STATUS_IN_USE);
});

it('moves room and marks old room to inspect when usage is unknown', function (): void {
    $setup = makeRoomMoveSetup('move-unknown.serena.test');

    $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $setup['newRoom']->id,
                'vacated_usage' => 'unknown',
            ],
        )
        ->assertOk();

    $setup['oldRoom']->refresh();
    $setup['newRoom']->refresh();

    expect($setup['oldRoom']->status)->toBe(Room::STATUS_AVAILABLE)
        ->and($setup['oldRoom']->hk_status)->toBe(Room::HK_STATUS_AWAITING_INSPECTION)
        ->and($setup['newRoom']->status)->toBe(Room::STATUS_IN_USE);
});

it('cannot move to a room from another hotel', function (): void {
    $setup = makeRoomMoveSetup('move-cross.serena.test');

    $otherHotel = Hotel::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'name' => 'Other Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $otherRoomType = RoomType::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $otherHotel->getKey(),
        'name' => 'Other Type',
        'capacity_adults' => 2,
        'capacity_children' => 0,
        'base_price' => 12000,
    ]);

    $otherRoom = Room::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $otherHotel->getKey(),
        'room_type_id' => $otherRoomType->getKey(),
        'number' => '201',
        'status' => Room::STATUS_AVAILABLE,
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $otherRoom->id,
                'vacated_usage' => 'not_used',
            ],
        )
        ->assertNotFound();
});
