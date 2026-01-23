<?php

namespace App\Services;

use App\Models\HotelLoyaltySetting;
use App\Models\Reservation;
use Illuminate\Support\Carbon;

class LoyaltyEarningService
{
    public function computeEarnedPoints(Reservation $reservation): int
    {
        if ($reservation->status !== Reservation::STATUS_CHECKED_OUT) {
            return 0;
        }

        $settings = HotelLoyaltySetting::query()
            ->where('tenant_id', $reservation->tenant_id)
            ->where('hotel_id', $reservation->hotel_id)
            ->first();

        if (! $settings || ! $settings->enabled) {
            return 0;
        }

        $points = match ($settings->earning_mode) {
            'amount' => $this->pointsFromAmount($reservation, $settings),
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

    private function pointsFromAmount(Reservation $reservation, HotelLoyaltySetting $settings): int
    {
        $pointsPerAmount = (int) ($settings->points_per_amount ?? 0);
        $amountBase = (float) ($settings->amount_base ?? 0);

        if ($pointsPerAmount <= 0 || $amountBase <= 0) {
            return 0;
        }

        $total = (float) ($reservation->total_amount ?? 0);

        if ($total <= 0) {
            return 0;
        }

        return (int) floor($total / $amountBase) * $pointsPerAmount;
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
}
