<?php

use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Validation\ValidationException;

it('requires hourly offers to end the same day', function (): void {
    $reservation = new Reservation([
        'check_in_date' => '2025-06-01 10:00:00',
        'check_out_date' => '2025-06-02 12:00:00',
        'offer_kind' => 'hourly',
    ]);

    expect(fn () => $reservation->validateOfferDates())->toThrow(ValidationException::class);
});

it('requires at least one night for night offers', function (): void {
    $reservation = new Reservation([
        'check_in_date' => '2025-07-01',
        'check_out_date' => '2025-07-01',
        'offer_kind' => 'night',
    ]);

    expect(fn () => $reservation->validateOfferDates())->toThrow(ValidationException::class);
});

it('requires two nights for weekend offers', function (): void {
    $reservation = new Reservation([
        'check_in_date' => '2025-08-01',
        'check_out_date' => '2025-08-02',
        'offer_kind' => 'weekend',
    ]);

    expect(fn () => $reservation->validateOfferDates())->toThrow(ValidationException::class);
});

it('uses package minimum nights when provided', function (): void {
    $reservation = new Reservation([
        'check_in_date' => '2025-09-01',
        'check_out_date' => '2025-09-02',
        'offer_kind' => 'package',
    ]);

    $offer = new Offer;
    $offer->forceFill(['min_nights' => 3]);

    $reservation->setRelation('offer', $offer);

    expect(fn () => $reservation->validateOfferDates())->toThrow(ValidationException::class);
});
