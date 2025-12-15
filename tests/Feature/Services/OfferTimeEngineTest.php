<?php

use App\Models\Offer;
use App\Services\OfferTimeEngine;
use Illuminate\Support\Carbon;

it('computes rolling offer period', function (): void {
    $offer = new Offer([
        'time_rule' => 'rolling',
        'time_config' => [
            'duration_minutes' => 120,
        ],
    ]);

    $engine = new OfferTimeEngine;
    $arrival = Carbon::parse('2025-01-01 10:00:00');

    $result = $engine->computeStayPeriod($offer, $arrival);

    expect($result['arrival_at']->toDateTimeString())->toBe('2025-01-01 10:00:00')
        ->and($result['departure_at']->toDateTimeString())->toBe('2025-01-01 12:00:00');
});

it('computes fixed checkout offer period', function (): void {
    $offer = new Offer([
        'time_rule' => 'fixed_checkout',
        'time_config' => [
            'checkout_time' => '12:00',
            'day_offset' => 1,
        ],
    ]);

    $engine = new OfferTimeEngine;
    $arrival = Carbon::parse('2025-01-01 15:30:00');

    $result = $engine->computeStayPeriod($offer, $arrival);

    expect($result['departure_at']->toDateTimeString())->toBe('2025-01-02 12:00:00');
});

it('computes fixed checkout with night cutoff for early arrival', function (): void {
    $offer = new Offer([
        'time_rule' => 'fixed_checkout',
        'time_config' => [
            'checkout_time' => '12:00',
            'day_offset' => 1,
            'night_cutoff_time' => '06:00',
        ],
    ]);

    $engine = new OfferTimeEngine;
    $arrival = Carbon::parse('2025-03-13 02:00:00');

    $result = $engine->computeStayPeriod($offer, $arrival);

    expect($result['departure_at']->toDateTimeString())->toBe('2025-03-13 12:00:00');
});

it('computes fixed checkout with night cutoff for late arrival', function (): void {
    $offer = new Offer([
        'time_rule' => 'fixed_checkout',
        'time_config' => [
            'checkout_time' => '12:00',
            'day_offset' => 1,
            'night_cutoff_time' => '06:00',
        ],
    ]);

    $engine = new OfferTimeEngine;
    $arrival = Carbon::parse('2025-03-13 08:00:00');

    $result = $engine->computeStayPeriod($offer, $arrival);

    expect($result['departure_at']->toDateTimeString())->toBe('2025-03-14 12:00:00');
});

it('computes fixed checkout with night cutoff boundary treated as after cutoff', function (): void {
    $offer = new Offer([
        'time_rule' => 'fixed_checkout',
        'time_config' => [
            'checkout_time' => '12:00',
            'day_offset' => 1,
            'night_cutoff_time' => '06:00',
        ],
    ]);

    $engine = new OfferTimeEngine;
    $arrival = Carbon::parse('2025-03-13 06:00:00');

    $result = $engine->computeStayPeriod($offer, $arrival);

    expect($result['departure_at']->toDateTimeString())->toBe('2025-03-14 12:00:00');
});
