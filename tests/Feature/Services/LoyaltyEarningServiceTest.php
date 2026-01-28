<?php

require_once __DIR__.'/../FolioTestHelpers.php';

use App\Models\HotelLoyaltySetting;
use App\Models\PaymentMethod;
use App\Services\FolioBillingService;
use App\Services\LoyaltyEarningService;
use Illuminate\Support\Carbon;

it('calculates points based on amount with floor rounding', function (): void {
    ['reservation' => $reservation] = setupReservationEnvironment('loyalty-amount');

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
        'amount' => 5500,
        'currency' => $reservation->currency,
        'payment_method_id' => $paymentMethod->id,
        'created_by_user_id' => $reservation->booked_by_user_id,
    ]);

    $points = app(LoyaltyEarningService::class)->computeEarnedPoints($reservation->fresh());

    expect($points)->toBe(5);
});

it('calculates points based on nights stayed', function (): void {
    ['reservation' => $reservation] = setupReservationEnvironment('loyalty-nights');

    $reservation->forceFill([
        'check_in_date' => Carbon::parse('2025-01-01 15:00:00'),
        'check_out_date' => Carbon::parse('2025-01-04 10:00:00'),
    ])->save();

    HotelLoyaltySetting::query()->create([
        'tenant_id' => $reservation->tenant_id,
        'hotel_id' => $reservation->hotel_id,
        'enabled' => true,
        'earning_mode' => 'nights',
        'points_per_night' => 10,
    ]);

    $paymentMethod = PaymentMethod::query()
        ->where('tenant_id', $reservation->tenant_id)
        ->where('hotel_id', $reservation->hotel_id)
        ->where('code', 'CASH')
        ->firstOrFail();

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);

    $folio->addPayment([
        'amount' => 1000,
        'currency' => $reservation->currency,
        'payment_method_id' => $paymentMethod->id,
        'created_by_user_id' => $reservation->booked_by_user_id,
    ]);

    $points = app(LoyaltyEarningService::class)->computeEarnedPoints($reservation->fresh());

    expect($points)->toBe(30);
});

it('applies the max points per stay cap', function (): void {
    ['reservation' => $reservation] = setupReservationEnvironment('loyalty-cap');

    HotelLoyaltySetting::query()->create([
        'tenant_id' => $reservation->tenant_id,
        'hotel_id' => $reservation->hotel_id,
        'enabled' => true,
        'earning_mode' => 'fixed',
        'fixed_points' => 120,
        'max_points_per_stay' => 100,
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

    $points = app(LoyaltyEarningService::class)->computeEarnedPoints($reservation->fresh());

    expect($points)->toBe(100);
});

it('returns zero when loyalty is disabled', function (): void {
    ['reservation' => $reservation] = setupReservationEnvironment('loyalty-disabled');

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

    $points = app(LoyaltyEarningService::class)->computeEarnedPoints($reservation->fresh());

    expect($points)->toBe(0);
});
