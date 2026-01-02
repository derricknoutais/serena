<?php

declare(strict_types=1);

namespace App\Services\Offers;

use App\Exceptions\OfferNotValidForDateTimeException;
use App\Models\Offer;
use App\Models\Reservation;
use Carbon\Carbon;

class OfferReservationService
{
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

        [$startAt, $endAt] = $this->resolveStartAndEnd($offer, $dt, $customEnd);

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

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveStartAndEnd(Offer $offer, Carbon $dt, ?Carbon $customEnd): array
    {
        $start = $dt->copy();

        if ($offer->check_in_from) {
            [$h, $m] = $this->parseTimeString($offer->check_in_from);

            if ($dt->lt($dt->copy()->setTime($h, $m))) {
                $start = $dt->copy()->setTime($h, $m, 0, 0);
            }
        }

        if ($offer->fixed_duration_hours !== null) {
            $end = $start->copy()->addHours((int) $offer->fixed_duration_hours);
        } else {
            $end = $customEnd?->copy() ?? $start->copy();
        }

        return [$start, $end];
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

        $dayOfWeek = $dt->dayOfWeekIso;
        $validDays = $offer->valid_days_of_week ?? [];

        if (is_array($validDays) && $validDays !== [] && ! in_array($dayOfWeek, $validDays, true)) {
            return $returnMessage ? 'Cette offre n’est pas valable pour ce jour de la semaine.' : 'invalid';
        }

        $start = $dt->copy();
        $end = null;

        if ($offer->fixed_duration_hours !== null) {
            if ($offer->check_in_from && $offer->check_out_until) {
                [$inH, $inM] = $this->parseTimeString($offer->check_in_from);
                [$outH, $outM] = $this->parseTimeString($offer->check_out_until);

                $startLimit = $dt->copy()->setTime($inH, $inM);
                $endLimit = $dt->copy()->setTime($outH, $outM);

                if ($endLimit->lessThanOrEqualTo($startLimit)) {
                    $endLimit->addDay();
                }

                if ($dt->lt($startLimit)) {
                    $start = $startLimit;
                } elseif ($dt->gt($endLimit)) {
                    return $returnMessage ? 'Cette offre n’est plus disponible pour aujourd’hui.' : 'invalid';
                }
            } elseif ($offer->check_in_from) {
                [$inH, $inM] = $this->parseTimeString($offer->check_in_from);
                $startLimit = $dt->copy()->setTime($inH, $inM);

                if ($dt->lt($startLimit)) {
                    $start = $startLimit;
                }
            }

            $end = $start->copy()->addHours((int) $offer->fixed_duration_hours);

            if ($offer->check_out_until) {
                [$outH, $outM] = $this->parseTimeString($offer->check_out_until);
                $limit = $start->copy()->setTime($outH, $outM);

                if ($limit->lessThanOrEqualTo($start)) {
                    $limit->addDay();
                }

                if ($end->gt($limit)) {
                    return $returnMessage ? 'La durée de cette offre dépasse l’heure limite de départ.' : 'invalid';
                }
            }
        } else {
            if ($offer->check_in_from) {
                [$inH, $inM] = $this->parseTimeString($offer->check_in_from);
                $minStart = $dt->copy()->setTime($inH, $inM);

                if ($dt->lt($minStart)) {
                    return $returnMessage ? 'Cette offre ne permet pas un check-in aussi tôt.' : 'invalid';
                }
            }

            if ($customEnd && $offer->check_out_until) {
                [$outH, $outM] = $this->parseTimeString($offer->check_out_until);
                $limit = $customEnd->copy()->setTime($outH, $outM);

                if ($customEnd->gt($limit)) {
                    return $returnMessage ? 'Cette offre ne permet pas un départ aussi tard.' : 'invalid';
                }
            }
        }

        return null;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseTimeString(string $time): array
    {
        [$hStr, $mStr] = explode(':', $time.'::');

        return [max(0, (int) $hStr), max(0, (int) $mStr)];
    }
}
