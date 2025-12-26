<?php

use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

test('walk-in reservation returns a redirect for inertia requests', function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    Carbon::setTestNow(Carbon::create(2025, 12, 24, 10, 0, 0));

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Walkin Hotel',
        'slug' => 'walkin-hotel',
        'plan' => 'standard',
        'contact_email' => 'contact@walkin.test',
        'data' => [
            'name' => 'Walkin Hotel',
            'slug' => 'walkin-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'walkin.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Walkin Main',
    ]);

    $roomType = RoomType::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->id,
        'name' => 'Standard',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 10000,
    ]);

    $room = Room::query()->create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '101',
        'status' => 'active',
        'hk_status' => 'clean',
    ]);

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->id,
        'name' => 'Tarif standard',
        'kind' => 'night',
        'billing_mode' => 'fixed',
        'time_rule' => 'rolling',
        'time_config' => ['duration_minutes' => 1440],
        'is_active' => true,
    ]);

    $offerPrice = OfferRoomTypePrice::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->id,
        'offer_id' => $offer->id,
        'room_type_id' => $roomType->id,
        'currency' => 'XAF',
        'price' => 12000,
    ]);

    $guest = Guest::query()->create([
        'tenant_id' => $tenant->getKey(),
        'first_name' => 'Jean',
        'last_name' => 'Walkin',
        'email' => 'walkin@example.com',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'email' => 'frontdesk@walkin.test',
        'active_hotel_id' => $hotel->id,
    ]);
    $user->assignRole('receptionist');

    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post('http://walkin.serena.test/frontdesk/room-board/walk-in', [
            'guest_id' => $guest->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'offer_id' => $offer->id,
            'offer_price_id' => $offerPrice->id,
            'adults' => 1,
            'children' => 0,
            'amount_received' => 0,
            'check_in_at' => '2025-12-24 09:30:00',
            'check_out_at' => '2025-12-25 11:00:00',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    tenancy()->initialize($tenant);

    $reservation = Reservation::query()->firstOrFail();
    expect($reservation->code)->toBe('RSV-2512001');
    expect($reservation->check_in_date?->format('Y-m-d H:i:s'))->toBe('2025-12-24 09:30:00');
    expect($reservation->check_out_date?->format('Y-m-d H:i:s'))->toBe('2025-12-25 11:00:00');
    expect($reservation->actual_check_in_at?->format('Y-m-d H:i:s'))->toBe('2025-12-24 09:30:00');
    expect($room->refresh()->hk_status)->toBe('clean');

    Carbon::setTestNow();
});

test('walk-in reservation requires amount received', function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Walkin Hotel',
        'slug' => 'walkin-hotel-amount',
        'plan' => 'standard',
        'contact_email' => 'contact@walkin.test',
        'data' => [
            'name' => 'Walkin Hotel',
            'slug' => 'walkin-hotel-amount',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'walkin-amount.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Walkin Main',
    ]);

    $roomType = RoomType::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->id,
        'name' => 'Standard',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 10000,
    ]);

    $room = Room::query()->create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '101',
        'status' => 'active',
        'hk_status' => 'clean',
    ]);

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->id,
        'name' => 'Tarif standard',
        'kind' => 'night',
        'billing_mode' => 'fixed',
        'time_rule' => 'rolling',
        'time_config' => ['duration_minutes' => 1440],
        'is_active' => true,
    ]);

    $offerPrice = OfferRoomTypePrice::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->id,
        'offer_id' => $offer->id,
        'room_type_id' => $roomType->id,
        'currency' => 'XAF',
        'price' => 12000,
    ]);

    $guest = Guest::query()->create([
        'tenant_id' => $tenant->getKey(),
        'first_name' => 'Jean',
        'last_name' => 'Walkin',
        'email' => 'walkin@example.com',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'email' => 'frontdesk@walkin.test',
        'active_hotel_id' => $hotel->id,
    ]);
    $user->assignRole('receptionist');

    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post('http://walkin-amount.serena.test/frontdesk/room-board/walk-in', [
            'guest_id' => $guest->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'offer_id' => $offer->id,
            'offer_price_id' => $offerPrice->id,
            'adults' => 1,
            'children' => 0,
        ]);

    $response->assertSessionHasErrors(['amount_received']);
});
