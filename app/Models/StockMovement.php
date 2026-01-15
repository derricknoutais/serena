<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_CONSUME = 'consume';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_RETURN = 'return';

    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'movement_type',
        'occurred_at',
        'from_location_id',
        'to_location_id',
        'reference_type',
        'reference_id',
        'created_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class, 'stock_movement_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'to_location_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
