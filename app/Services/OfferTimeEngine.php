<?php

namespace App\Services;

use App\Models\Offer;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OfferTimeEngine
{
    /**
     * @return array{arrival_at: Carbon, departure_at: Carbon}
     */
    public function computeStayPeriod(Offer $offer, Carbon $arrivalAt): array
    {
        $rule = $offer->time_rule ?? 'rolling';
        $config = $offer->time_config ?? [];

        return match ($rule) {
            'rolling' => $this->computeRolling($arrivalAt, $config),
            'fixed_window' => $this->computeFixedWindow($arrivalAt, $config),
            'fixed_checkout' => $this->computeFixedCheckout($arrivalAt, $config),
            'weekend_window' => $this->computeWeekendWindow($arrivalAt, $config),
            default => throw ValidationException::withMessages([
                'offer' => 'Règles de temps de l’offre non configurées.',
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{arrival_at: Carbon, departure_at: Carbon}
     */
    protected function computeRolling(Carbon $arrivalAt, array $config): array
    {
        $minutes = (int) ($config['duration_minutes'] ?? 0);

        if ($minutes <= 0) {
            throw ValidationException::withMessages([
                'offer' => 'Durée de l’offre invalide.',
            ]);
        }

        $departure = $arrivalAt->copy()->addMinutes($minutes);

        return [
            'arrival_at' => $arrivalAt,
            'departure_at' => $departure,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{arrival_at: Carbon, departure_at: Carbon}
     */
    protected function computeFixedWindow(Carbon $arrivalAt, array $config): array
    {
        $startTime = (string) ($config['start_time'] ?? '');
        $endTime = (string) ($config['end_time'] ?? '');

        if ($startTime === '' || $endTime === '') {
            throw ValidationException::withMessages([
                'offer' => 'Fenêtre horaire de l’offre invalide.',
            ]);
        }

        [$startHour, $startMinute] = $this->parseTime($startTime);
        [$endHour, $endMinute] = $this->parseTime($endTime);

        $start = $arrivalAt->copy()->setTime($startHour, $startMinute);
        $end = $arrivalAt->copy()->setTime($endHour, $endMinute);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        if ($arrivalAt->lessThan($start)) {
            $arrivalAt = $start;
        } elseif ($arrivalAt->greaterThan($end)) {
            throw ValidationException::withMessages([
                'offer' => 'Cette offre n’est plus disponible pour aujourd’hui.',
            ]);
        }

        return [
            'arrival_at' => $arrivalAt,
            'departure_at' => $end,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{arrival_at: Carbon, departure_at: Carbon}
     */
    protected function computeFixedCheckout(Carbon $arrivalAt, array $config): array
    {
        $checkoutTime = (string) ($config['checkout_time'] ?? '');

        if ($checkoutTime === '') {
            throw ValidationException::withMessages([
                'offer' => 'Heure de départ de l’offre invalide.',
            ]);
        }

        [$h, $m] = $this->parseTime($checkoutTime);
        $dayOffset = (int) ($config['day_offset'] ?? 1);

        if ($dayOffset <= 0) {
            $dayOffset = 1;
        }

        $baseDate = $arrivalAt->copy();

        $nightCutoffTime = $config['night_cutoff_time'] ?? null;

        if ($nightCutoffTime !== null && $nightCutoffTime !== '') {
            if (! is_string($nightCutoffTime)) {
                throw ValidationException::withMessages([
                    'offer' => 'Heure limite d’arrivée (cutoff) invalide pour cette offre.',
                ]);
            }

            [$cutoffHour, $cutoffMinute] = $this->parseTime($nightCutoffTime);

            $arrivalTimeMinutes = ($arrivalAt->hour * 60) + $arrivalAt->minute;
            $cutoffMinutes = ($cutoffHour * 60) + $cutoffMinute;

            if ($arrivalTimeMinutes < $cutoffMinutes) {
                $baseDate->subDay();
            }
        }

        $departure = $baseDate->copy()->addDays($dayOffset)->setTime($h, $m);

        return [
            'arrival_at' => $arrivalAt,
            'departure_at' => $departure,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{arrival_at: Carbon, departure_at: Carbon}
     */
    protected function computeWeekendWindow(Carbon $arrivalAt, array $config): array
    {
        $allowed = $config['checkin']['allowed_weekdays'] ?? [];
        $startTime = $config['checkin']['start_time'] ?? null;
        $checkoutTime = $config['checkout']['time'] ?? null;
        $maxDays = (int) ($config['checkout']['max_days_after_checkin'] ?? 0);

        if (! is_array($allowed) || $startTime === null || $checkoutTime === null || $maxDays <= 0) {
            throw ValidationException::withMessages([
                'offer' => 'Configuration du week-end invalide pour cette offre.',
            ]);
        }

        $weekday = $arrivalAt->dayOfWeekIso;
        if (! in_array($weekday, $allowed, true)) {
            throw ValidationException::withMessages([
                'offer' => 'Cette offre n’est pas valable pour ce jour de la semaine.',
            ]);
        }

        [$inH, $inM] = $this->parseTime($startTime);
        $minStart = $arrivalAt->copy()->setTime($inH, $inM);

        if ($arrivalAt->lessThan($minStart)) {
            throw ValidationException::withMessages([
                'offer' => 'Cette offre ne permet pas un check-in aussi tôt.',
            ]);
        }

        [$outH, $outM] = $this->parseTime($checkoutTime);
        $departure = $arrivalAt->copy()->addDays($maxDays)->setTime($outH, $outM);

        return [
            'arrival_at' => $arrivalAt,
            'departure_at' => $departure,
        ];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseTime(string $time): array
    {
        [$h, $m] = explode(':', $time.':0');

        return [(int) $h, (int) $m];
    }
}
