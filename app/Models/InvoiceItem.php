<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'folio_item_id',
        'description',
        'quantity',
        'unit_price',
        'tax_amount',
        'total_amount',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'tax_amount' => 'float',
            'total_amount' => 'float',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function folioItem(): BelongsTo
    {
        return $this->belongsTo(FolioItem::class);
    }
}
