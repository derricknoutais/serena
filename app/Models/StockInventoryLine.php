<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInventoryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'stock_inventory_id',
        'stock_item_id',
        'counted_quantity',
        'system_quantity',
        'variance_quantity',
    ];

    protected function casts(): array
    {
        return [
            'counted_quantity' => 'decimal:2',
            'system_quantity' => 'decimal:2',
            'variance_quantity' => 'decimal:2',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(StockInventory::class, 'stock_inventory_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
