<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\HotelLoyaltySetting;
use App\Models\Reservation;
use App\Services\ReservationStateMachine;

it('creates loyalty points when checking out', function (): void {
    ['reservation' => $reservation] = setupReservationEnvironment('loyalty-checkout');

    $reservation->forceFill([
        'status' => Reservation::STATUS_IN_HOUSE,
        'actual_check_in_at' => now()->subDay(),
        'total_amount' => 12000,
    ])->save();

    HotelLoyaltySetting::query()->create([
        'tenant_id' => $reservation->tenant_id,
        'hotel_id' => $reservation->hotel_id,
        'enabled' => true,
        'earning_mode' => 'amount',
        'points_per_amount' => 1,
        'amount_base' => 1000,
    ]);

    $stateMachine = app(ReservationStateMachine::class);
    $stateMachine->checkOut($reservation->fresh());

    $this->assertDatabaseHas('loyalty_points', [
        'reservation_id' => $reservation->id,
        'guest_id' => $reservation->guest_id,
        'points' => 12,
        'type' => 'earn',
    ]);
});

it('does not create loyalty points when disabled', function (): void {
    ['reservation' => $reservation] = setupReservationEnvironment('loyalty-checkout-disabled');

    $reservation->forceFill([
        'status' => Reservation::STATUS_IN_HOUSE,
        'actual_check_in_at' => now()->subDay(),
    ])->save();

    HotelLoyaltySetting::query()->create([
        'tenant_id' => $reservation->tenant_id,
        'hotel_id' => $reservation->hotel_id,
        'enabled' => false,
        'earning_mode' => 'fixed',
        'fixed_points' => 50,
    ]);

    $stateMachine = app(ReservationStateMachine::class);
    $stateMachine->checkOut($reservation->fresh());

    expect(
        \App\Models\LoyaltyPoint::query()->where('reservation_id', $reservation->id)->exists()
    )->toBeFalse();
});
