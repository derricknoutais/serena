<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\HotelLoyaltySetting;
use App\Models\PaymentMethod;
use App\Services\FolioBillingService;

it('creates loyalty points when a payment is recorded', function (): void {
    ['reservation' => $reservation] = setupReservationEnvironment('loyalty-checkout');

    HotelLoyaltySetting::query()->create([
        'tenant_id' => $reservation->tenant_id,
        'hotel_id' => $reservation->hotel_id,
        'enabled' => true,
        'earning_mode' => 'amount',
        'points_per_amount' => 1,
        'amount_base' => 1000,
    ]);

    $paymentMethod = PaymentMethod::query()
        ->where('tenant_id', $reservation->tenant_id)
        ->where('hotel_id', $reservation->hotel_id)
        ->where('code', 'CASH')
        ->firstOrFail();

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);

    $folio->addPayment([
        'amount' => 12000,
        'currency' => $reservation->currency,
        'payment_method_id' => $paymentMethod->id,
        'created_by_user_id' => $reservation->booked_by_user_id,
    ]);

    $this->assertDatabaseHas('loyalty_points', [
        'reservation_id' => $reservation->id,
        'guest_id' => $reservation->guest_id,
        'points' => 12,
        'type' => 'earn',
    ]);
});

it('does not create loyalty points when disabled', function (): void {
    ['reservation' => $reservation] = setupReservationEnvironment('loyalty-checkout-disabled');

    HotelLoyaltySetting::query()->create([
        'tenant_id' => $reservation->tenant_id,
        'hotel_id' => $reservation->hotel_id,
        'enabled' => false,
        'earning_mode' => 'fixed',
        'fixed_points' => 50,
    ]);

    $paymentMethod = PaymentMethod::query()
        ->where('tenant_id', $reservation->tenant_id)
        ->where('hotel_id', $reservation->hotel_id)
        ->where('code', 'CASH')
        ->firstOrFail();

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);

    $folio->addPayment([
        'amount' => 5000,
        'currency' => $reservation->currency,
        'payment_method_id' => $paymentMethod->id,
        'created_by_user_id' => $reservation->booked_by_user_id,
    ]);

    expect(
        \App\Models\LoyaltyPoint::query()->where('reservation_id', $reservation->id)->exists()
    )->toBeFalse();
});
