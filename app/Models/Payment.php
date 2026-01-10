<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessDate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use function activity;

class Payment extends Model
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
        'payment_method_id',
        'amount',
        'currency',
        'paid_at',
        'reference',
        'notes',
        'created_by_user_id',
        'cash_session_id',
        'business_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_at' => 'datetime',
            'business_date' => 'date',
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

    protected static function booted(): void
    {
        static::deleting(function (Payment $payment): void {
            if (! $payment->isForceDeleting()) {
                activity('payment')
                    ->performedOn($payment)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'method' => $payment->paymentMethod?->name,
                        'cash_session_id' => $payment->cash_session_id,
                    ])
                    ->event('voided')
                    ->log('voided');
            }
        });
    }

    protected function businessDateReferenceTime(): CarbonInterface
    {
        return $this->normalizeBusinessDateTime($this->paid_at ?? $this->created_at);
    }
}
