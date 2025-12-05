<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'folio_id',
        'guest_id',
        'number',
        'status',
        'issue_date',
        'due_date',
        'currency',
        'sub_total',
        'tax_total',
        'total_amount',
        'billing_name',
        'billing_address',
        'billing_tax_id',
        'notes',
        'meta',
        'created_by_user_id',
        'cancelled_by_user_id',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
