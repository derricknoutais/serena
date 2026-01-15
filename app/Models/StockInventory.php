<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockInventory extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'storage_location_id',
        'status',
        'counted_at',
        'created_by_user_id',
        'posted_at',
        'posted_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'counted_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockInventoryLine::class, 'stock_inventory_id');
    }
}
