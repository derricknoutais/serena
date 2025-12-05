<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'name',
        'description',
        'is_active',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
