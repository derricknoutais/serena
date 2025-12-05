<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_AVAILABLE = 'active';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_OUT_OF_ORDER = 'out_of_order';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'room_type_id',
        'number',
        'floor',
        'status',
        'hk_status',
    ];

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class);
    }
}
