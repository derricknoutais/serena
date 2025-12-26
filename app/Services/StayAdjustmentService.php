<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Folio;
use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Support\Carbon;

class StayAdjustmentService
{
    public function __construct(
        private readonly FolioBillingService $billingService,
    ) {}

    /**
     * @return array{
     *     is_early_checkin: bool,
     *     early_fee_amount: float,
     *     early_reason: ?string,
     *     early_policy: string,
     *     early_blocked: bool,
     *     is_late_checkout: bool,
     *     late_fee_amount: float,
     *     late_reason: ?string,
     *     late_policy: string,
     *     late_blocked: bool,
     *     late_fee_type: string|null,
     *     late_fee_value: float,
     *     late_minutes: int,
     *     late_grace_minutes: int,
     *     late_expected_at: string|null,
     *     currency: string|null
     * }
     */
    public function evaluateEarlyLate(Reservation $reservation, Carbon $actualCheckInAt, ?Carbon $actualCheckOutAt = null): array
    {
        $hotel = $reservation->hotel ?? Hotel::query()->find($reservation->hotel_id);

        $settings = $hotel?->stay_settings ?? [];
        $standardCheckIn = $settings['standard_checkin_time'] ?? $hotel?->check_in_time ?? '14:00';
        $standardCheckOut = $settings['standard_checkout_time'] ?? $hotel?->check_out_time ?? '12:00';

        $earlyConfig = $settings['early_checkin'] ?? [];
        $lateConfig = $settings['late_checkout'] ?? [];

        $offerLateConfig = $reservation->offer?->time_config['late_checkout'] ?? null;

        $decision = [
            'is_early_checkin' => false,
            'early_fee_amount' => 0.0,
            'early_reason' => null,
            'early_policy' => $earlyConfig['policy'] ?? 'free',
            'early_blocked' => false,
            'is_late_checkout' => false,
            'late_fee_amount' => 0.0,
            'late_reason' => null,
            'late_policy' => $lateConfig['policy'] ?? 'free',
            'late_blocked' => false,
            'late_fee_type' => $lateConfig['fee_type'] ?? 'flat',
            'late_fee_value' => (float) ($lateConfig['fee_value'] ?? 0),
            'late_minutes' => 0,
            'late_grace_minutes' => 0,
            'late_expected_at' => null,
            'currency' => $reservation->currency,
        ];

        $checkInTime = $standardCheckIn ? $this->timeOnDate($actualCheckInAt, $standardCheckIn) : null;
        $checkOutTime = $standardCheckOut && $actualCheckOutAt ? $this->timeOnDate($actualCheckOutAt, $standardCheckOut) : null;

        if ($checkInTime && $actualCheckInAt->lt($checkInTime)) {
            $decision['is_early_checkin'] = true;
            $decision['early_reason'] = sprintf(
                'Arrivée avant l’heure standard (%s).',
                $checkInTime->format('H:i')
            );

            $cutoff = $earlyConfig['cutoff_time'] ?? null;
            if ($cutoff) {
                $cutoffTime = $this->timeOnDate($actualCheckInAt, $cutoff);
                if ($cutoffTime && $actualCheckInAt->lt($cutoffTime)) {
                    $decision['early_reason'] = sprintf(
                        'Arrivée avant %s.',
                        $cutoffTime->format('H:i')
                    );
                }
            }

            $policy = $decision['early_policy'];
            if ($policy === 'forbidden') {
                $decision['early_blocked'] = true;
            } elseif ($policy === 'paid') {
                $feeType = $earlyConfig['fee_type'] ?? 'flat';
                $feeValue = (float) ($earlyConfig['fee_value'] ?? 0);
                $decision['early_fee_amount'] = $this->calculateFee($feeType, $feeValue, (float) $reservation->base_amount);
            }
        }

        $lateCheckOutAt = null;
        $latePolicy = $decision['late_policy'];
        $lateFeeType = $lateConfig['fee_type'] ?? 'flat';
        $lateFeeValue = (float) ($lateConfig['fee_value'] ?? 0);
        $lateMaxTime = $lateConfig['max_time'] ?? null;
        $lateMinutes = 0;

        $expectedCheckout = $reservation->check_out_date
            ? Carbon::parse($reservation->check_out_date)
            : null;
        if ($expectedCheckout) {
            $decision['late_expected_at'] = $expectedCheckout->toDateTimeString();
        }

        $useOfferLateConfig = $actualCheckOutAt
            && $expectedCheckout
            && is_array($offerLateConfig)
            && (($offerLateConfig['policy'] ?? null) !== 'inherit');

        if ($useOfferLateConfig) {
            $offerPolicy = $offerLateConfig['policy'] ?? 'free';
            $latePolicy = $offerPolicy;
            $lateFeeType = $offerLateConfig['fee_type'] ?? $lateFeeType;
            $lateFeeValue = (float) ($offerLateConfig['fee_value'] ?? $lateFeeValue);

            $graceMinutes = (int) ($offerLateConfig['grace_minutes'] ?? 0);
            $decision['late_grace_minutes'] = $graceMinutes;
            $lateCheckOutAt = $expectedCheckout->copy()->addMinutes($graceMinutes);
            if ($actualCheckOutAt->gt($lateCheckOutAt)) {
                $decision['is_late_checkout'] = true;
                $decision['late_reason'] = $graceMinutes > 0
                    ? sprintf('Départ après la tolérance (%s).', $lateCheckOutAt->format('H:i'))
                    : sprintf('Départ après l’heure prévue (%s).', $expectedCheckout->format('H:i'));
                $lateMinutes = (int) $lateCheckOutAt->diffInMinutes($actualCheckOutAt);
            }
        }

        if (! $useOfferLateConfig && $actualCheckOutAt && $checkOutTime && $actualCheckOutAt->gt($checkOutTime)) {
            $decision['is_late_checkout'] = true;
            $decision['late_reason'] = sprintf(
                'Départ après l’heure standard (%s).',
                $checkOutTime->format('H:i')
            );
            $lateMinutes = (int) $checkOutTime->diffInMinutes($actualCheckOutAt);

            $maxTime = $lateMaxTime;
            if ($maxTime) {
                $maxTimeParsed = $this->timeOnDate($actualCheckOutAt, $maxTime);
                if ($maxTimeParsed && $actualCheckOutAt->gt($maxTimeParsed)) {
                    $decision['late_reason'] = sprintf(
                        'Départ au-delà de la limite (%s).',
                        $maxTimeParsed->format('H:i')
                    );
                }
            }
        }

        if ($decision['is_late_checkout']) {
            $decision['late_policy'] = $latePolicy ?? $decision['late_policy'];
            $decision['late_fee_type'] = $lateFeeType;
            $decision['late_fee_value'] = (float) $lateFeeValue;
            $decision['late_minutes'] = $lateMinutes;
            $policy = $decision['late_policy'];
            if ($policy === 'forbidden') {
                $decision['late_blocked'] = true;
            } elseif ($policy === 'paid') {
                $decision['late_fee_amount'] = $this->calculateLateFee(
                    $lateFeeType,
                    $lateFeeValue,
                    (float) $reservation->base_amount,
                    $lateMinutes,
                );
            }
        }

        return $decision;
    }

    public function applyFeesToFolio(
        Reservation $reservation,
        array $decision,
        ?float $earlyOverride,
        ?float $lateOverride,
        bool $canOverride,
    ): void {
        if (! $decision['is_early_checkin'] && ! $decision['is_late_checkout']) {
            return;
        }

        $folio = $this->billingService->ensureMainFolioForReservation($reservation);

        if ($decision['is_early_checkin'] && ($decision['early_fee_amount'] > 0 || ($canOverride && $earlyOverride !== null))) {
            $amount = $this->resolveAmount($decision['early_fee_amount'], $earlyOverride, $canOverride);
            $this->addFeeIfMissing(
                $folio,
                'Arrivée anticipée',
                $amount,
                'early_checkin',
                $decision['early_reason'],
            );
        }

        if ($decision['is_late_checkout'] && ($decision['late_fee_amount'] > 0 || ($canOverride && $lateOverride !== null))) {
            $amount = $this->resolveAmount($decision['late_fee_amount'], $lateOverride, $canOverride);
            $this->addFeeIfMissing(
                $folio,
                'Départ tardif',
                $amount,
                'late_checkout',
                $decision['late_reason'],
            );
        }
    }

    private function resolveAmount(float $computed, ?float $override, bool $canOverride): float
    {
        if ($canOverride && $override !== null && $override >= 0) {
            return (float) $override;
        }

        return max(0.0, $computed);
    }

    private function addFeeIfMissing(Folio $folio, string $description, float $amount, string $kind, ?string $reason): void
    {
        if ($amount <= 0) {
            return;
        }

        $alreadyExists = $folio->items()
            ->where('type', 'service_fee')
            ->where('meta->kind', $kind)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $folio->addCharge([
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'type' => 'service_fee',
            'tax_amount' => 0,
            'meta' => [
                'kind' => $kind,
                'reason' => $reason,
            ],
        ]);
    }

    private function calculateFee(string $type, float $value, float $baseAmount): float
    {
        if ($type === 'percent') {
            return round($baseAmount * ($value / 100), 2);
        }

        return round($value, 2);
    }

    private function calculateLateFee(string $type, float $value, float $baseAmount, int $minutesLate): float
    {
        if ($minutesLate <= 0) {
            return 0.0;
        }

        return match ($type) {
            'per_hour' => round((int) ceil($minutesLate / 60) * $value, 2),
            'per_day' => round((int) ceil($minutesLate / 1440) * $value, 2),
            'percent' => round($baseAmount * ($value / 100), 2),
            default => round($value, 2),
        };
    }

    private function timeOnDate(Carbon $dateTime, string $timeString): ?Carbon
    {
        if (! $timeString) {
            return null;
        }

        return $dateTime->copy()->setTimeFromTimeString($timeString);
    }
}
