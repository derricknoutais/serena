<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageLocation extends Model
{
    use HasFactory;

    protected $table = 'storage_locations';

    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'name',
        'code',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function fromStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'from_location_id');
    }

    public function toStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'to_location_id');
    }
}
