<?php

use App\Models\CashSession;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Payment;
use App\Models\PaymentMethod;
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
        'hk_status' => Room::HK_STATUS_INSPECTED,
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
    expect($room->refresh()->hk_status)->toBe(Room::HK_STATUS_INSPECTED);

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
        'hk_status' => Room::HK_STATUS_INSPECTED,
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
        ]);

    $response->assertSessionHasErrors(['amount_received']);
});

test('walk-in reservation requires a payment method when amount is received', function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Walkin Hotel',
        'slug' => 'walkin-hotel-payment',
        'plan' => 'standard',
        'contact_email' => 'contact@walkin.test',
        'data' => [
            'name' => 'Walkin Hotel',
            'slug' => 'walkin-hotel-payment',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'walkin-payment.serena.test']);

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
        'hk_status' => Room::HK_STATUS_INSPECTED,
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

    PaymentMethod::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->id,
        'name' => 'Espèces',
        'code' => 'CASH',
        'type' => 'cash',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'email' => 'frontdesk@walkin.test',
        'active_hotel_id' => $hotel->id,
    ]);
    $user->assignRole('receptionist');

    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post('http://walkin-payment.serena.test/frontdesk/room-board/walk-in', [
            'guest_id' => $guest->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'offer_id' => $offer->id,
            'offer_price_id' => $offerPrice->id,
            'amount_received' => 5000,
        ]);

    $response->assertSessionHasErrors(['payment_method_id']);
});

test('walk-in reservation links non-cash payments to the active cash session', function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Walkin Hotel',
        'slug' => 'walkin-hotel-session',
        'plan' => 'standard',
        'contact_email' => 'contact@walkin.test',
        'data' => [
            'name' => 'Walkin Hotel',
            'slug' => 'walkin-hotel-session',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'walkin-session.serena.test']);

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
        'hk_status' => Room::HK_STATUS_INSPECTED,
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

    $paymentMethod = PaymentMethod::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->id,
        'name' => 'Carte bancaire',
        'code' => 'CARD',
        'type' => 'card',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'email' => 'frontdesk@walkin.test',
        'active_hotel_id' => $hotel->id,
    ]);
    $user->assignRole('receptionist');

    $cashSession = CashSession::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->id,
        'type' => 'frontdesk',
        'opened_by_user_id' => $user->id,
        'started_at' => now(),
        'starting_amount' => 0,
        'currency' => 'XAF',
        'status' => 'open',
    ]);

    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post('http://walkin-session.serena.test/frontdesk/room-board/walk-in', [
            'guest_id' => $guest->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'offer_id' => $offer->id,
            'offer_price_id' => $offerPrice->id,
            'amount_received' => 5000,
            'payment_method_id' => $paymentMethod->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    tenancy()->initialize($tenant);

    $payment = Payment::query()->firstOrFail();
    expect($payment->cash_session_id)->toBe($cashSession->id);
});
