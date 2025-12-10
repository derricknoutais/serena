<?php

namespace App\Models;

use App\Services\ReservationStateMachine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class Reservation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_HOUSE = 'in_house';

    public const STATUS_CHECKED_OUT = 'checked_out';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'guest_id',
        'room_type_id',
        'room_id',
        'offer_id',
        'code',
        'status',
        'source',
        'offer_name',
        'offer_kind',
        'adults',
        'children',
        'check_in_date',
        'check_out_date',
        'expected_arrival_time',
        'actual_check_in_at',
        'actual_check_out_at',
        'currency',
        'unit_price',
        'base_amount',
        'tax_amount',
        'total_amount',
        'notes',
        'booked_by_user_id',
    ];

    protected $casts = [
        'check_in_date' => 'datetime',
        'check_out_date' => 'datetime',
        'actual_check_in_at' => 'datetime',
        'actual_check_out_at' => 'datetime',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'status_label',
    ];

    public static function statusOptions(): array
    {
        return self::statuses();
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_IN_HOUSE,
            self::STATUS_CHECKED_OUT,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
        ];
    }

    /**
     * @return list<string>
     */
    public static function activeStatusForAvailability(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_IN_HOUSE,
        ];
    }

    public function canTransition(string $to): bool
    {
        return app(ReservationStateMachine::class)->canTransition($this->status, $to);
    }

    public function validateOfferDates(): void
    {
        if (! $this->check_in_date || ! $this->check_out_date) {
            return;
        }

        $checkIn = $this->check_in_date instanceof Carbon
            ? $this->check_in_date->copy()
            : Carbon::parse($this->check_in_date);
        $checkOut = $this->check_out_date instanceof Carbon
            ? $this->check_out_date->copy()
            : Carbon::parse($this->check_out_date);

        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw ValidationException::withMessages([
                'check_out_date' => 'La date de départ doit être postérieure à la date d’arrivée.',
            ]);
        }

        $nights = $checkIn->diffInDays($checkOut);

        switch ($this->offer_kind) {
            case 'hourly':
                if ($checkIn->toDateString() !== $checkOut->toDateString()) {
                    throw ValidationException::withMessages([
                        'check_out_date' => 'Cette offre horaire doit commencer et se terminer le même jour.',
                    ]);
                }

                break;

            case 'night':
                if ($nights < 1) {
                    throw ValidationException::withMessages([
                        'check_out_date' => 'Cette offre nuitée nécessite au moins une nuit complète.',
                    ]);
                }

                break;

            case 'day':
                if ($nights < 1) {
                    throw ValidationException::withMessages([
                        'check_out_date' => 'Cette offre 24h nécessite au moins un jour complet.',
                    ]);
                }

                break;

            case 'weekend':
                if ($nights < 2) {
                    throw ValidationException::withMessages([
                        'check_out_date' => 'Cette offre week-end nécessite au moins deux nuits.',
                    ]);
                }

                break;

            case 'package':
                $minNights = $this->offer?->min_nights ?? 2;
                if ($minNights && $nights < $minNights) {
                    throw ValidationException::withMessages([
                        'check_out_date' => 'Cette offre nécessite au moins '.$minNights.' nuit(s).',
                    ]);
                }

                break;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'En attente',
            self::STATUS_CONFIRMED => 'Confirmée',
            self::STATUS_IN_HOUSE => 'En séjour',
            self::STATUS_CHECKED_OUT => 'Départ effectué',
            self::STATUS_CANCELLED => 'Annulée',
            self::STATUS_NO_SHOW => 'No-show',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }

    public function mainFolio(): HasOne
    {
        return $this->hasOne(Folio::class)->where('is_main', true);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForHotel(Builder $query, int $hotelId): Builder
    {
        return $query->where('hotel_id', $hotelId);
    }

    public function scopeArrivalsOn(Builder $query, mixed $date): Builder
    {
        return $query->whereDate('check_in_date', $date)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function scopeDeparturesOn(Builder $query, mixed $date): Builder
    {
        return $query->whereDate('check_out_date', $date)
            ->where('status', self::STATUS_IN_HOUSE);
    }

    public function scopeInHouseOn(Builder $query, mixed $date): Builder
    {
        return $query->where('status', self::STATUS_IN_HOUSE)
            ->whereDate('check_in_date', '<=', $date)
            ->whereDate('check_out_date', '>=', $date);
    }

    public function canCheckIn(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
        ], true);
    }

    public function canCheckOut(): bool
    {
        return $this->status === self::STATUS_IN_HOUSE;
    }

    public function isInHouse(): bool
    {
        return $this->status === self::STATUS_IN_HOUSE;
    }
}
