<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FolioItem extends Model
{
    use HasBusinessDate;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'folio_id',
        'is_stay_item',
        'product_id',
        'date',
        'description',
        'type',
        'account_code',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'net_amount',
        'base_amount',
        'total_amount',
        'tax_amount',
        'meta',
        'business_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'business_date' => 'date',
            'quantity' => 'float',
            'unit_price' => 'float',
            'discount_percent' => 'float',
            'discount_amount' => 'float',
            'net_amount' => 'float',
            'is_stay_item' => 'boolean',
            'base_amount' => 'float',
            'tax_amount' => 'float',
            'total_amount' => 'float',
            'meta' => 'array',
        ];
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function recalculateAmounts(): void
    {
        $quantity = (float) $this->quantity;
        $unitPrice = (float) $this->unit_price;
        $taxAmount = (float) $this->tax_amount;

        $base = $quantity * $unitPrice;
        $this->base_amount = $base;

        $discountPercent = max(0.0, (float) $this->discount_percent);
        $discountAmount = max(0.0, (float) $this->discount_amount);

        $absoluteBase = abs($base);

        if ($discountAmount <= 0 && $discountPercent > 0 && $absoluteBase > 0) {
            $discountAmount = round($absoluteBase * ($discountPercent / 100), 2);
        }

        if ($absoluteBase > 0 && $discountAmount > $absoluteBase) {
            $discountAmount = $absoluteBase;
        }

        $netAmount = $base >= 0
            ? $base - $discountAmount
            : $base + $discountAmount;

        $this->discount_amount = $discountAmount;
        $this->net_amount = $netAmount;
        $this->total_amount = $netAmount + $taxAmount;
    }
}
