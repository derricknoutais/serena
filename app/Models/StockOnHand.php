<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOnHand extends Model
{
    use HasFactory;

    protected $table = 'stock_on_hand';

    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'storage_location_id',
        'stock_item_id',
        'quantity_on_hand',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:2',
        ];
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
