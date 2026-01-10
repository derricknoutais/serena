<?php

declare(strict_types=1);

namespace App\Services\Offers;

use App\Exceptions\OfferNotValidForDateTimeException;
use App\Models\Offer;
use App\Models\Reservation;
use App\Services\OfferTimeEngine;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class OfferReservationService
{
    public function __construct(private readonly OfferTimeEngine $timeEngine) {}

    public function isOfferValidForDateTime(Offer $offer, Carbon $dt): bool
    {
        return ! $this->validateForDateTime($offer, $dt, null, false);
    }

    /**
     * @throws OfferNotValidForDateTimeException
     */
    public function buildReservationFromOffer(
        Offer $offer,
        Carbon $dt,
        string $resourceId,
        ?Carbon $customEnd = null,
        array $baseAttributes = []
    ): Reservation {
        $violation = $this->validateForDateTime($offer, $dt, $customEnd, true);

        if ($violation !== null) {
            throw new OfferNotValidForDateTimeException($violation);
        }

        [$startAt, $endAt] = $this->resolvePeriod($offer, $dt);

        $checkInDate = $startAt->copy();
        $checkOutDate = $endAt->copy();

        return new Reservation(array_merge($baseAttributes, [
            'room_id' => $resourceId,
            'offer_id' => $offer->id,
            'offer_name' => $offer->name,
            'offer_kind' => $offer->kind,
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
        ]));
    }

    private function resolvePeriod(Offer $offer, Carbon $dt): array
    {
        return $this->timeEngine->computeStayPeriod($offer, $dt);
    }

    private function validateForDateTime(
        Offer $offer,
        Carbon $dt,
        ?Carbon $customEnd,
        bool $returnMessage
    ): ?string {
        $date = $dt->toDateString();

        if ($offer->valid_from && $date < $offer->valid_from->toDateString()) {
            return $returnMessage ? 'Cette offre n’est pas encore valable à cette date.' : 'invalid';
        }

        if ($offer->valid_to && $date > $offer->valid_to->toDateString()) {
            return $returnMessage ? 'Cette offre n’est plus valable à cette date.' : 'invalid';
        }

        try {
            [$arrival, $departure] = $this->resolvePeriod($offer, $dt);
        } catch (ValidationException $e) {
            return $returnMessage ? ($e->getMessage() ?: 'Cette offre n’est pas disponible.') : 'invalid';
        }

        if ($customEnd && $customEnd->gt($departure)) {
            return $returnMessage ? 'La durée de cette offre dépasse l’heure limite de départ.' : 'invalid';
        }

        return null;
    }
}
