<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferRoomTypePrice extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'offer_id',
        'room_type_id',
        'currency',
        'price',
        'extra_adult_price',
        'extra_child_price',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (OfferRoomTypePrice $price): void {
            if (! empty($price->currency)) {
                return;
            }

            $hotel = $price->hotel ?? Hotel::query()->find($price->hotel_id);

            $price->currency = $hotel?->currency ?? 'XAF';
        });

        static::updating(function (OfferRoomTypePrice $price): void {
            if (! empty($price->currency)) {
                return;
            }

            $hotel = $price->hotel ?? Hotel::query()->find($price->hotel_id);

            $price->currency = $hotel?->currency ?? 'XAF';
        });
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
