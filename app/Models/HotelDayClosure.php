<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelDayClosure extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'business_date',
        'started_at',
        'closed_at',
        'closed_by_user_id',
        'status',
        'summary',
    ];

    /**
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
