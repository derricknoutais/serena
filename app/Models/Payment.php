<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'folio_id',
        'payment_method_id',
        'amount',
        'currency',
        'paid_at',
        'reference',
        'notes',
        'created_by_user_id',
        'cash_session_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_at' => 'datetime',
        ];
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }
}
