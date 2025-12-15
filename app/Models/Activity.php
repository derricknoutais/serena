<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    protected $fillable = [
        'log_name',
        'description',
        'tenant_id',
        'hotel_id',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'event',
        'batch_uuid',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            if (! $activity->tenant_id) {
                $activity->tenant_id = tenant('id') ?? auth()->user()?->tenant_id;
            }

            if (! $activity->hotel_id) {
                $activity->hotel_id = self::inferHotelId($activity);
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = tenant('id') ?? auth()->user()?->tenant_id;

            if ($tenantId) {
                $builder->where('tenant_id', (string) $tenantId);
            }
        });
    }

    private static function inferHotelId(Activity $activity): ?int
    {
        $subject = $activity->subject;

        if ($subject instanceof Reservation || $subject instanceof Room || $subject instanceof Folio || $subject instanceof Payment || $subject instanceof CashSession) {
            return $subject->hotel_id ?? null;
        }

        if (array_key_exists('hotel_id', $activity->attributesToArray())) {
            return (int) $activity->hotel_id;
        }

        return auth()->user()?->hotel_id;
    }
}
