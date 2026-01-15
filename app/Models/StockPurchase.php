<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockPurchase extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'storage_location_id',
        'reference_no',
        'supplier_name',
        'purchased_at',
        'status',
        'subtotal_amount',
        'total_amount',
        'currency',
        'created_by_user_id',
        'received_at',
        'received_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'received_at' => 'datetime',
            'subtotal_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
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

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockPurchaseLine::class, 'stock_purchase_id');
    }
}
