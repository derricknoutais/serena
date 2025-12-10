<?php

declare(strict_types=1);

use App\Exceptions\OfferNotValidForDateTimeException;
use App\Services\Offers\OfferReservationService;
use Illuminate\Support\Carbon;

it('accepts offer without restrictions for any datetime', function (): void {
    $service = new OfferReservationService;

    $offer = new \App\Models\Offer([
        'valid_from' => null,
        'valid_to' => null,
        'valid_days_of_week' => null,
        'check_in_from' => null,
        'check_out_until' => null,
        'fixed_duration_hours' => null,
    ]);

    $dt = Carbon::parse('2025-01-15 10:00:00');

    expect($service->isOfferValidForDateTime($offer, $dt))->toBeTrue();
});

it('rejects offer outside date range', function (): void {
    $service = new OfferReservationService;

    $offer = new \App\Models\Offer([
        'valid_from' => Carbon::parse('2025-01-10'),
        'valid_to' => Carbon::parse('2025-01-20'),
    ]);

    $before = Carbon::parse('2025-01-05 10:00:00');
    $after = Carbon::parse('2025-01-25 10:00:00');

    expect($service->isOfferValidForDateTime($offer, $before))->toBeFalse();
    expect($service->isOfferValidForDateTime($offer, $after))->toBeFalse();
});

it('validates days of week when provided', function (): void {
    $service = new OfferReservationService;

    $offer = new \App\Models\Offer([
        'valid_days_of_week' => [1, 2, 3],
    ]);

    $monday = Carbon::parse('2025-01-13 10:00:00'); // Monday
    $sunday = Carbon::parse('2025-01-12 10:00:00'); // Sunday

    expect($service->isOfferValidForDateTime($offer, $monday))->toBeTrue();
    expect($service->isOfferValidForDateTime($offer, $sunday))->toBeFalse();
});

it('enforces check in from for flexible duration offers', function (): void {
    $service = new OfferReservationService;

    $offer = new \App\Models\Offer([
        'fixed_duration_hours' => null,
        'check_in_from' => '14:00:00',
    ]);

    $tooEarly = Carbon::parse('2025-01-15 12:00:00');
    $lateEnough = Carbon::parse('2025-01-15 15:00:00');

    expect($service->isOfferValidForDateTime($offer, $tooEarly))->toBeFalse();
    expect($service->isOfferValidForDateTime($offer, $lateEnough))->toBeTrue();
});

it('computes end time for fixed duration offers and validates checkout limit', function (): void {
    $service = new OfferReservationService;

    $offer = new \App\Models\Offer([
        'fixed_duration_hours' => 3,
        'check_in_from' => '08:00:00',
        'check_out_until' => '12:00:00',
    ]);

    $dt = Carbon::parse('2025-01-15 07:00:00');

    expect($service->isOfferValidForDateTime($offer, $dt))->toBeTrue();

    $reservation = $service->buildReservationFromOffer($offer, $dt, 1);

    expect($reservation->check_in_date)->toEqual(Carbon::parse('2025-01-15')->startOfDay());
    expect($reservation->check_out_date)->toEqual(Carbon::parse('2025-01-15')->startOfDay());
});

it('throws when fixed duration exceeds checkout limit', function (): void {
    $service = new OfferReservationService;

    $offer = new \App\Models\Offer([
        'fixed_duration_hours' => 5,
        'check_in_from' => '08:00:00',
        'check_out_until' => '12:00:00',
    ]);

    $dt = Carbon::parse('2025-01-15 09:00:00');

    expect($service->isOfferValidForDateTime($offer, $dt))->toBeFalse();
    $service->buildReservationFromOffer($offer, $dt, 1);
})->throws(OfferNotValidForDateTimeException::class);
