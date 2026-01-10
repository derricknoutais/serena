<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Hotel;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BusinessDayService
{
    public function resolveBusinessDate(Hotel $hotel, CarbonInterface $when): CarbonImmutable
    {
        $timezone = $this->hotelTimezone($hotel);
        $startTime = $hotel->business_day_start_time ?? '08:00:00';
        $when = CarbonImmutable::instance($when)->setTimezone($timezone);

        $startOfWindow = $when->setTimeFromTimeString($startTime);
        if ($when->lt($startOfWindow)) {
            return $startOfWindow->subDay()->startOfDay();
        }

        return $startOfWindow->startOfDay();
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    public function businessWindow(Hotel $hotel, CarbonInterface $businessDate): array
    {
        $timezone = $this->hotelTimezone($hotel);
        $startTime = $hotel->business_day_start_time ?? '08:00:00';

        $windowStart = CarbonImmutable::parse($businessDate)->setTimezone($timezone)->setTimeFromTimeString($startTime);
        $windowEnd = $windowStart->addDay();

        return [$windowStart, $windowEnd];
    }

    private function hotelTimezone(Hotel $hotel): string
    {
        return $hotel->business_day_timezone
            ?? $hotel->timezone
            ?? config('app.timezone');
    }
}
