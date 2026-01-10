<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Hotel;
use App\Services\BusinessDayService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

trait HasBusinessDate
{
    protected static function bootHasBusinessDate(): void
    {
        static::creating(static function (Model $model): void {
            $model->ensureBusinessDate();
        });
    }

    protected function ensureBusinessDate(): void
    {
        if ($this->business_date) {
            return;
        }

        $hotel = $this->businessDateHotel();
        if (! $hotel) {
            return;
        }

        $reference = $this->businessDateReferenceTime();
        if (! $reference) {
            return;
        }

        $service = app(BusinessDayService::class);
        $this->business_date = $service->resolveBusinessDate($hotel, $reference)->toDateString();
    }

    protected function businessDateHotel(): ?Hotel
    {
        if ($this->relationLoaded('hotel') && $this->hotel instanceof Hotel) {
            return $this->hotel;
        }

        $hotelId = $this->businessDateHotelId();
        if (! $hotelId) {
            return null;
        }

        return Hotel::query()->find($hotelId);
    }

    protected function businessDateHotelId(): ?int
    {
        return $this->hotel_id ?? null;
    }

    protected function businessDateReferenceTime(): ?CarbonInterface
    {
        return $this->normalizeBusinessDateTime($this->created_at);
    }

    protected function normalizeBusinessDateTime(DateTimeInterface|string|null $value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value);
        }

        return CarbonImmutable::now();
    }
}
