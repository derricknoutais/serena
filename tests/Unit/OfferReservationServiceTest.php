<?php

declare(strict_types=1);

use App\Exceptions\OfferNotValidForDateTimeException;
use App\Models\Offer;
use App\Services\Offers\OfferReservationService;
use App\Services\OfferTimeEngine;
use Illuminate\Support\Carbon;

it('accepts rolling offers with a duration for any datetime', function (): void {
    $service = new OfferReservationService(new OfferTimeEngine);

    $offer = new Offer([
        'time_rule' => 'rolling',
        'time_config' => [
            'duration_minutes' => 120,
        ],
    ]);
    $dt = Carbon::parse('2025-01-15 10:00:00');

    expect($service->isOfferValidForDateTime($offer, $dt))->toBeTrue();
});

it('rejects offer outside date range', function (): void {
    $service = new OfferReservationService(new OfferTimeEngine);

    $offer = new Offer([
        'valid_from' => Carbon::parse('2025-01-10'),
        'valid_to' => Carbon::parse('2025-01-20'),
    ]);

    $before = Carbon::parse('2025-01-05 10:00:00');
    $after = Carbon::parse('2025-01-25 10:00:00');

    expect($service->isOfferValidForDateTime($offer, $before))->toBeFalse();
    expect($service->isOfferValidForDateTime($offer, $after))->toBeFalse();
});

it('enforces weekend window allowed weekdays', function (): void {
    $service = new OfferReservationService(new OfferTimeEngine);

    $offer = new Offer([
        'time_rule' => 'weekend_window',
        'time_config' => [
            'checkin' => [
                'allowed_weekdays' => [1, 2, 3],
                'start_time' => '10:00',
            ],
            'checkout' => [
                'time' => '12:00',
                'max_days_after_checkin' => 2,
            ],
        ],
    ]);

    $monday = Carbon::parse('2025-01-13 10:00:00');
    $sunday = Carbon::parse('2025-01-12 10:00:00');

    expect($service->isOfferValidForDateTime($offer, $monday))->toBeTrue();
    expect($service->isOfferValidForDateTime($offer, $sunday))->toBeFalse();
});

it('enforces fixed window start and end times', function (): void {
    $service = new OfferReservationService(new OfferTimeEngine);

    $offer = new Offer([
        'time_rule' => 'fixed_window',
        'time_config' => [
            'start_time' => '14:00',
            'end_time' => '18:00',
        ],
    ]);

    $before = Carbon::parse('2025-01-15 13:00:00');
    $after = Carbon::parse('2025-01-15 15:00:00');

    expect($service->isOfferValidForDateTime($offer, $before))->toBeTrue();
    expect($service->isOfferValidForDateTime($offer, $after))->toBeTrue();

    $reservation = $service->buildReservationFromOffer($offer, $after, 'room-1');

    expect($reservation->check_in_date)->toEqual($after);
    expect($reservation->check_out_date)->toEqual(Carbon::parse('2025-01-15 18:00:00'));
});

it('throws when custom end exceeds rolling duration', function (): void {
    $service = new OfferReservationService(new OfferTimeEngine);

    $offer = new Offer([
        'time_rule' => 'rolling',
        'time_config' => [
            'duration_minutes' => 180,
        ],
    ]);

    $dt = Carbon::parse('2025-01-15 10:00:00');

    expect($service->isOfferValidForDateTime($offer, $dt))->toBeTrue();

    $service->buildReservationFromOffer($offer, $dt, 'room-1');

    $lateEnd = $dt->copy()->addHours(5);

    expect(fn () => $service->buildReservationFromOffer($offer, $dt, 'room-1', $lateEnd))
        ->toThrow(OfferNotValidForDateTimeException::class);
});
