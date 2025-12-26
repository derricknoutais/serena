<?php

use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Frontdesk\RoomBoardData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function setupRoomBoardTenant(): array
{
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Tenant RoomBoard',
        'slug' => 'roomboard-tenant',
        'plan' => 'standard',
    ]);

    $tenant->createDomain(['domain' => 'roomboard-tenant.serena.test']);

    tenancy()->initialize($tenant);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hotel RoomBoard',
        'code' => 'HRB1',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'address' => 'Main street',
        'city' => 'Douala',
        'country' => 'CM',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $roomType = RoomType::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Deluxe',
        'code' => 'DLX',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 10000,
        'description' => 'Deluxe room',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'active_hotel_id' => $hotel->id,
        'email_verified_at' => now(),
    ]);

    $user->hotels()->attach($hotel);

    $guard = config('auth.defaults.guard', 'web');
    $permissions = [
        'maintenance_tickets.create',
        'maintenance_tickets.update',
        'maintenance_tickets.close',
    ];

    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => $guard,
        ]);
    }

    $user->givePermissionTo($permissions);

    return compact('tenant', 'hotel', 'roomType', 'user');
}

it('reflects occupied room status from database', function (): void {
    [
        'hotel' => $hotel,
        'roomType' => $roomType,
        'user' => $user,
    ] = setupRoomBoardTenant();

    $room = Room::query()->create([
        'tenant_id' => $user->tenant_id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '101',
        'floor' => '1',
        'status' => Room::STATUS_OCCUPIED,
        'hk_status' => 'clean',
    ]);

    $request = Request::create('/frontdesk/dashboard', 'GET', [
        'date' => now()->toDateString(),
    ]);
    $request->setUserResolver(fn () => $user);

    $data = RoomBoardData::build($request);
    $rooms = collect($data['roomsByFloor'])->flatten(1);
    $payload = $rooms->firstWhere('id', $room->id);

    expect($payload['ui_status'])->toBe('occupied');
});

it('marks inactive rooms as inactive in room board', function (): void {
    [
        'hotel' => $hotel,
        'roomType' => $roomType,
        'user' => $user,
    ] = setupRoomBoardTenant();

    $room = Room::query()->create([
        'tenant_id' => $user->tenant_id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '102',
        'floor' => '1',
        'status' => 'inactive',
        'hk_status' => 'clean',
    ]);

    $request = Request::create('/frontdesk/dashboard', 'GET', [
        'date' => now()->toDateString(),
    ]);
    $request->setUserResolver(fn () => $user);

    $data = RoomBoardData::build($request);
    $rooms = collect($data['roomsByFloor'])->flatten(1);
    $payload = $rooms->firstWhere('id', $room->id);

    expect($payload['ui_status'])->toBe('inactive');
});

it('includes current reservation details for occupied rooms', function (): void {
    [
        'hotel' => $hotel,
        'roomType' => $roomType,
        'user' => $user,
    ] = setupRoomBoardTenant();

    $room = Room::query()->create([
        'tenant_id' => $user->tenant_id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '103',
        'floor' => '1',
        'status' => Room::STATUS_OCCUPIED,
        'hk_status' => 'clean',
    ]);

    $guest = Guest::query()->create([
        'tenant_id' => $user->tenant_id,
        'first_name' => 'Alex',
        'last_name' => 'Durand',
    ]);

    Reservation::query()->create([
        'tenant_id' => $user->tenant_id,
        'hotel_id' => $hotel->id,
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'room_id' => $room->id,
        'offer_id' => null,
        'code' => 'RSV-2512001',
        'status' => Reservation::STATUS_IN_HOUSE,
        'source' => 'walk_in',
        'offer_name' => null,
        'offer_kind' => 'night',
        'adults' => 1,
        'children' => 0,
        'check_in_date' => now()->startOfDay(),
        'check_out_date' => now()->addDay()->startOfDay(),
        'actual_check_in_at' => now(),
        'actual_check_out_at' => null,
        'currency' => 'XAF',
        'unit_price' => 10000,
        'base_amount' => 10000,
        'tax_amount' => 0,
        'total_amount' => 10000,
    ]);

    $request = Request::create('/frontdesk/dashboard', 'GET', [
        'date' => now()->toDateString(),
    ]);
    $request->setUserResolver(fn () => $user);

    $data = RoomBoardData::build($request);
    $rooms = collect($data['roomsByFloor'])->flatten(1);
    $payload = $rooms->firstWhere('id', $room->id);

    expect($payload['current_reservation'])->not->toBeNull();
    expect($payload['current_reservation']['code'])->toBe('RSV-2512001');
    expect($payload['current_reservation']['check_in_at'])->toBe(now()->startOfDay()->toDateTimeString());
    expect($payload['current_reservation']['check_out_at'])->toBe(now()->addDay()->startOfDay()->toDateTimeString());
});

it('shows in-house reservations even after the checkout date', function (): void {
    [
        'hotel' => $hotel,
        'roomType' => $roomType,
        'user' => $user,
    ] = setupRoomBoardTenant();

    $room = Room::query()->create([
        'tenant_id' => $user->tenant_id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '104',
        'floor' => '1',
        'status' => Room::STATUS_OCCUPIED,
        'hk_status' => 'dirty',
    ]);

    $guest = Guest::query()->create([
        'tenant_id' => $user->tenant_id,
        'first_name' => 'Mina',
        'last_name' => 'Kane',
    ]);

    Reservation::query()->create([
        'tenant_id' => $user->tenant_id,
        'hotel_id' => $hotel->id,
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'room_id' => $room->id,
        'offer_id' => null,
        'code' => 'RSV-2512002',
        'status' => Reservation::STATUS_IN_HOUSE,
        'source' => 'walk_in',
        'offer_name' => null,
        'offer_kind' => 'night',
        'adults' => 1,
        'children' => 0,
        'check_in_date' => now()->subDays(6)->startOfDay(),
        'check_out_date' => now()->subDays(3)->startOfDay(),
        'actual_check_in_at' => now()->subDays(6)->addHours(2),
        'actual_check_out_at' => null,
        'currency' => 'XAF',
        'unit_price' => 10000,
        'base_amount' => 10000,
        'tax_amount' => 0,
        'total_amount' => 10000,
    ]);

    $request = Request::create('/frontdesk/dashboard', 'GET', [
        'date' => now()->toDateString(),
    ]);
    $request->setUserResolver(fn () => $user);

    $data = RoomBoardData::build($request);
    $rooms = collect($data['roomsByFloor'])->flatten(1);
    $payload = $rooms->firstWhere('id', $room->id);

    expect($payload['current_reservation'])->not->toBeNull();
    expect($payload['current_reservation']['is_overstay'])->toBeTrue();
});
