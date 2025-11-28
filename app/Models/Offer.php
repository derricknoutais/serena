<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Offer extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'name',
        'code',
        'kind',
        'fixed_duration_hours',
        'billing_mode',
        'check_in_from',
        'check_out_until',
        'valid_days_of_week',
        'valid_from',
        'valid_to',
        'is_active',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_days_of_week' => 'array',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(OfferRoomTypePrice::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function roomTypes(): HasManyThrough
    {
        return $this->hasManyThrough(
            RoomType::class,
            OfferRoomTypePrice::class,
            'offer_id',
            'id',
            'id',
            'room_type_id'
        );
    }
}
