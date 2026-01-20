<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'product_category_id',
        'name',
        'sku',
        'unit_price',
        'tax_id',
        'is_active',
        'stock_item_id',
        'manage_stock',
        'stock_quantity_per_unit',
        'stock_location_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'manage_stock' => 'boolean',
            'stock_quantity_per_unit' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'stock_location_id');
    }

    public function folioItems(): HasMany
    {
        return $this->hasMany(FolioItem::class);
    }
}
