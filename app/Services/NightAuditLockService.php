<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelDayClosure;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class NightAuditLockService
{
    public function __construct(private readonly BusinessDayService $businessDayService) {}

    public function businessWindow(Hotel $hotel, CarbonInterface $businessDate): array
    {
        return $this->businessDayService->businessWindow($hotel, $businessDate);
    }

    public function closureFor(Hotel $hotel, CarbonInterface $businessDate): ?HotelDayClosure
    {
        return HotelDayClosure::query()
            ->where('hotel_id', $hotel->id)
            ->whereDate('business_date', $businessDate->toDateString())
            ->first();
    }

    public function isClosed(Hotel $hotel, CarbonInterface $businessDate): bool
    {
        return $this->closureFor($hotel, $businessDate)?->status === HotelDayClosure::STATUS_CLOSED;
    }

    public function assertBusinessDateOpen(Hotel $hotel, CarbonInterface $businessDate, ?User $actor, bool $override = false): void
    {
        if (! $this->isClosed($hotel, $businessDate)) {
            return;
        }

        if ($override && $this->canOverride($actor)) {
            return;
        }

        abort(423, 'La journée d’affaires est verrouillée.');
    }

    public function closeDay(Hotel $hotel, CarbonInterface $businessDate, array $summary, ?User $closedBy = null): HotelDayClosure
    {
        [$windowStart] = $this->businessWindow($hotel, $businessDate);

        return HotelDayClosure::query()->updateOrCreate(
            [
                'hotel_id' => $hotel->id,
                'business_date' => $businessDate->toDateString(),
            ],
            [
                'tenant_id' => $hotel->tenant_id,
                'started_at' => $windowStart->toDateTimeString(),
                'closed_at' => Carbon::now(),
                'closed_by_user_id' => $closedBy?->id,
                'status' => HotelDayClosure::STATUS_CLOSED,
                'summary' => $summary,
            ],
        );
    }

    public function reopenDay(Hotel $hotel, CarbonInterface $businessDate, ?User $user = null): HotelDayClosure
    {
        [$windowStart] = $this->businessWindow($hotel, $businessDate);

        $closure = HotelDayClosure::query()->firstOrNew([
            'hotel_id' => $hotel->id,
            'business_date' => $businessDate->toDateString(),
        ]);

        $closure->tenant_id = $hotel->tenant_id;
        $closure->started_at = $windowStart->toDateTimeString();
        $closure->closed_at = null;
        $closure->closed_by_user_id = null;
        $closure->status = HotelDayClosure::STATUS_OPEN;
        $closure->summary = $closure->summary ?? [];
        $closure->save();

        return $closure;
    }

    protected function canOverride(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['owner', 'manager', 'superadmin']);
    }
}
