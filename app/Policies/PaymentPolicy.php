<?php

namespace App\Policies;

use App\Models\Hotel;
use App\Models\Payment;
use App\Models\User;
use App\Services\BusinessDayService;
use App\Services\NightAuditLockService;
use Carbon\CarbonInterface;

class PaymentPolicy
{
    public function void(User $user, Payment $payment): bool
    {
        if (! $this->belongsToUserContext($user, $payment)) {
            return false;
        }

        if (! $user->hasPermissionTo('payments.void')) {
            return false;
        }

        $businessDate = $this->resolveBusinessDate($payment);

        if (! $businessDate) {
            return false;
        }

        return $this->isBusinessDateOpen($user, $payment, $businessDate);
    }

    public function refund(User $user, Payment $payment): bool
    {
        if (! $this->belongsToUserContext($user, $payment)) {
            return false;
        }

        if (! $user->hasPermissionTo('payments.refund')) {
            return false;
        }

        $hotel = $this->resolveHotel($payment);

        if (! $hotel) {
            return false;
        }

        $businessDate = app(BusinessDayService::class)->resolveBusinessDate($hotel, now());

        return $this->isBusinessDateOpen($user, $payment, $businessDate);
    }

    private function belongsToUserContext(User $user, Payment $payment): bool
    {
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        return $payment->tenant_id === $user->tenant_id
            && (int) $payment->hotel_id === $hotelId;
    }

    private function isBusinessDateOpen(User $user, Payment $payment, CarbonInterface $businessDate): bool
    {
        $hotel = $this->resolveHotel($payment);

        if (! $hotel) {
            return false;
        }

        $lockService = app(NightAuditLockService::class);

        if (! $lockService->isClosed($hotel, $businessDate)) {
            return true;
        }

        return $user->hasPermissionTo('payments.override_closed_day');
    }

    private function resolveHotel(Payment $payment): ?Hotel
    {
        if ($payment->relationLoaded('hotel') && $payment->hotel) {
            return $payment->hotel;
        }

        return Hotel::query()->find($payment->hotel_id);
    }

    private function resolveBusinessDate(Payment $payment): ?CarbonInterface
    {
        if ($payment->business_date) {
            return $payment->business_date;
        }

        $hotel = $this->resolveHotel($payment);

        if (! $hotel) {
            return null;
        }

        $reference = $payment->paid_at ?? $payment->created_at ?? now();

        return app(BusinessDayService::class)->resolveBusinessDate($hotel, $reference);
    }
}
