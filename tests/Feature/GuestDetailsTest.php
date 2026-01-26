<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Folio;
use App\Models\LoyaltyPoint;
use App\Models\Payment;
use App\Models\Reservation;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('shows guest details with reservations and payments', function (): void {
    $this->seed(PermissionSeeder::class);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'roomType' => $roomType,
        'room' => $room,
        'guest' => $guest,
        'reservation' => $reservation,
        'user' => $user,
        'methods' => $methods,
    ] = setupReservationEnvironment('guest-show');

    $user->givePermissionTo('guests.view');

    $reservation->update([
        'status' => Reservation::STATUS_CHECKED_OUT,
        'check_in_date' => '2026-01-01 14:00:00',
        'check_out_date' => '2026-01-04 12:00:00',
    ]);

    $recentReservation = Reservation::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'room_id' => $room->id,
        'offer_id' => null,
        'code' => 'RES-NEW',
        'status' => Reservation::STATUS_IN_HOUSE,
        'source' => 'direct',
        'offer_name' => null,
        'offer_kind' => null,
        'adults' => 2,
        'children' => 0,
        'check_in_date' => '2026-02-01 14:00:00',
        'check_out_date' => '2026-02-03 12:00:00',
        'expected_arrival_time' => '14:00',
        'actual_check_in_at' => null,
        'actual_check_out_at' => null,
        'currency' => $hotel->currency,
        'unit_price' => 10000,
        'base_amount' => 20000,
        'tax_amount' => 0,
        'total_amount' => 20000,
        'notes' => null,
        'booked_by_user_id' => $user->id,
    ]);

    $folio = Folio::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'reservation_id' => $recentReservation->id,
        'guest_id' => $guest->id,
        'code' => 'FOL-GUEST-1',
        'status' => 'open',
        'is_main' => true,
        'type' => 'stay',
        'origin' => 'reservation',
        'currency' => $hotel->currency,
        'billing_name' => $guest->full_name,
        'opened_at' => now(),
    ]);
    $folio->forceFill(['balance' => 0])->save();

    Payment::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'folio_id' => $folio->id,
        'payment_method_id' => $methods[0]->id,
        'amount' => 20000,
        'currency' => $hotel->currency,
        'paid_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    $domain = tenantDomain($tenant);

    $this->actingAs($user)
        ->get("http://{$domain}/guests/{$guest->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Frontdesk/Guests/Show')
            ->where('guest.id', $guest->id)
            ->has('reservations', 2)
            ->where('reservations.0.payments.0.amount', 20000)
        );
});

it('returns guest summary with loyalty and analytics', function (): void {
    $this->seed(PermissionSeeder::class);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'roomType' => $roomType,
        'room' => $room,
        'guest' => $guest,
        'reservation' => $reservation,
        'user' => $user,
        'methods' => $methods,
    ] = setupReservationEnvironment('guest-summary');

    $user->givePermissionTo('guests.view');

    $reservation->update([
        'status' => Reservation::STATUS_CHECKED_OUT,
        'check_in_date' => '2026-01-01 14:00:00',
        'check_out_date' => '2026-01-04 12:00:00',
    ]);

    $secondReservation = Reservation::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'room_id' => $room->id,
        'offer_id' => null,
        'code' => 'RES-SUM',
        'status' => Reservation::STATUS_CONFIRMED,
        'source' => 'direct',
        'offer_name' => null,
        'offer_kind' => null,
        'adults' => 2,
        'children' => 0,
        'check_in_date' => '2026-02-01 14:00:00',
        'check_out_date' => '2026-02-03 12:00:00',
        'expected_arrival_time' => '14:00',
        'actual_check_in_at' => null,
        'actual_check_out_at' => null,
        'currency' => $hotel->currency,
        'unit_price' => 10000,
        'base_amount' => 20000,
        'tax_amount' => 0,
        'total_amount' => 20000,
        'notes' => null,
        'booked_by_user_id' => $user->id,
    ]);

    $folioA = Folio::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'reservation_id' => $reservation->id,
        'guest_id' => $guest->id,
        'code' => 'FOL-SUM-1',
        'status' => 'open',
        'is_main' => true,
        'type' => 'stay',
        'origin' => 'reservation',
        'currency' => $hotel->currency,
        'billing_name' => $guest->full_name,
        'opened_at' => now(),
    ]);
    $folioA->forceFill(['balance' => 5000])->save();

    Payment::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'folio_id' => $folioA->id,
        'payment_method_id' => $methods[0]->id,
        'amount' => 15000,
        'currency' => $hotel->currency,
        'paid_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    $folioB = Folio::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'reservation_id' => $secondReservation->id,
        'guest_id' => $guest->id,
        'code' => 'FOL-SUM-2',
        'status' => 'open',
        'is_main' => true,
        'type' => 'stay',
        'origin' => 'reservation',
        'currency' => $hotel->currency,
        'billing_name' => $guest->full_name,
        'opened_at' => now(),
    ]);
    $folioB->forceFill(['balance' => 0])->save();

    Payment::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'folio_id' => $folioB->id,
        'payment_method_id' => $methods[0]->id,
        'amount' => 20000,
        'currency' => $hotel->currency,
        'paid_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    LoyaltyPoint::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'reservation_id' => $reservation->id,
        'guest_id' => $guest->id,
        'type' => 'stay',
        'points' => 120,
    ]);

    LoyaltyPoint::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'reservation_id' => $secondReservation->id,
        'guest_id' => $guest->id,
        'type' => 'stay',
        'points' => 50,
    ]);

    $domain = tenantDomain($tenant);

    $this->actingAs($user)
        ->get("http://{$domain}/guests/{$guest->id}/summary")
        ->assertOk()
        ->assertJsonFragment([
            'reservations_total' => 2,
            'total_nights' => 5,
            'total_spent' => 35000.0,
            'balance_due' => 5000.0,
            'total_points' => 170,
        ]);
});
