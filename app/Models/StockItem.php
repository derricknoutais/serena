<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    use HasFactory;

    protected $table = 'stock_items';

    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'name',
        'sku',
        'unit',
        'item_category',
        'is_active',
        'default_purchase_price',
        'currency',
        'reorder_point',
        'is_kit',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_purchase_price' => 'decimal:2',
            'reorder_point' => 'decimal:2',
            'is_kit' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function movementLines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class, 'stock_item_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(StockItemComponent::class, 'kit_stock_item_id');
    }

    public function isKit(): bool
    {
        return (bool) $this->is_kit;
    }
}
