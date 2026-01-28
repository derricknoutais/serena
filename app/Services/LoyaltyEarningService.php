<?php

namespace App\Services;

use App\Models\HotelLoyaltySetting;
use App\Models\LoyaltyPoint;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Carbon;

class LoyaltyEarningService
{
    public function computeEarnedPoints(Reservation $reservation): int
    {
        $settings = HotelLoyaltySetting::query()
            ->where('tenant_id', $reservation->tenant_id)
            ->where('hotel_id', $reservation->hotel_id)
            ->first();

        if (! $settings || ! $settings->enabled) {
            return 0;
        }

        $totalPaid = $this->totalPaidForReservation($reservation);

        if ($totalPaid <= 0) {
            return 0;
        }

        $points = match ($settings->earning_mode) {
            'amount' => $this->pointsFromPaidAmount($totalPaid, $settings),
            'nights' => $this->pointsFromNights($reservation, $settings),
            'fixed' => (int) ($settings->fixed_points ?? 0),
            default => 0,
        };

        if ($points <= 0) {
            return 0;
        }

        $max = $settings->max_points_per_stay;

        if ($max !== null && $max > 0) {
            $points = min($points, $max);
        }

        return max(0, (int) $points);
    }

    public function recordPointsForReservation(Reservation $reservation): int
    {
        if (! $reservation->guest_id) {
            return 0;
        }

        $points = $this->computeEarnedPoints($reservation);

        if ($points <= 0) {
            return 0;
        }

        $alreadyEarned = LoyaltyPoint::query()
            ->where('reservation_id', $reservation->id)
            ->where('type', 'earn')
            ->sum('points');

        $delta = $points - (int) $alreadyEarned;

        if ($delta <= 0) {
            return 0;
        }

        LoyaltyPoint::query()->create([
            'tenant_id' => $reservation->tenant_id,
            'hotel_id' => $reservation->hotel_id,
            'reservation_id' => $reservation->id,
            'guest_id' => $reservation->guest_id,
            'type' => 'earn',
            'points' => $delta,
        ]);

        return $delta;
    }

    private function pointsFromPaidAmount(float $totalPaid, HotelLoyaltySetting $settings): int
    {
        $pointsPerAmount = (int) ($settings->points_per_amount ?? 0);
        $amountBase = (float) ($settings->amount_base ?? 0);

        if ($pointsPerAmount <= 0 || $amountBase <= 0) {
            return 0;
        }

        return (int) floor($totalPaid / $amountBase) * $pointsPerAmount;
    }

    private function pointsFromNights(Reservation $reservation, HotelLoyaltySetting $settings): int
    {
        $pointsPerNight = (int) ($settings->points_per_night ?? 0);

        if ($pointsPerNight <= 0) {
            return 0;
        }

        $checkIn = $reservation->check_in_date instanceof Carbon
            ? $reservation->check_in_date->copy()
            : ($reservation->check_in_date ? Carbon::parse($reservation->check_in_date) : null);
        $checkOut = $reservation->check_out_date instanceof Carbon
            ? $reservation->check_out_date->copy()
            : ($reservation->check_out_date ? Carbon::parse($reservation->check_out_date) : null);

        if (! $checkIn || ! $checkOut) {
            return 0;
        }

        $nights = $checkIn->startOfDay()->diffInDays($checkOut->startOfDay());

        if ($nights <= 0) {
            return 0;
        }

        return $nights * $pointsPerNight;
    }

    private function totalPaidForReservation(Reservation $reservation): float
    {
        $folio = $reservation->relationLoaded('mainFolio')
            ? $reservation->mainFolio
            : $reservation->mainFolio()->first();

        if (! $folio) {
            return 0;
        }

        return (float) $folio->payments()
            ->where('amount', '>', 0)
            ->where(function ($query): void {
                $query
                    ->whereNull('entry_type')
                    ->orWhere('entry_type', Payment::ENTRY_TYPE_PAYMENT);
            })
            ->sum('amount');
    }
}
