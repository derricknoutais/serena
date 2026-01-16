<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockItemComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'kit_stock_item_id',
        'component_stock_item_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function kit(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'kit_stock_item_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'component_stock_item_id');
    }
}
