<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessDate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use function activity;

class CashSession extends Model
{
    use HasBusinessDate;

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'validated_at' => 'datetime',
        'starting_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'expected_closing_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'business_date' => 'date',
    ];

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Calculate the theoretical balance
     */
    public function calculateTheoreticalBalance(): float
    {
        $transactionsTotal = $this->transactions()->sum('amount');
        $paymentsTotal = $this->payments()->sum('amount');

        return (float) $this->starting_amount + $transactionsTotal + $paymentsTotal;
    }

    public function calculateTotalReceived(): float
    {
        $transactionsTotal = $this->transactions()
            ->where('amount', '>', 0)
            ->sum('amount');

        $paymentsTotal = $this->payments()->sum('amount');

        return (float) $transactionsTotal + $paymentsTotal;
    }

    public function scopeOpen(Builder $query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosedPending(Builder $query)
    {
        return $query->where('status', 'closed_pending_validation');
    }

    protected static function booted(): void
    {
        static::created(function (CashSession $session): void {
            activity('cash_session')
                ->performedOn($session)
                ->causedBy(auth()->user())
                ->withProperties([
                    'pos' => $session->type,
                    'opening_amount' => $session->starting_amount,
                    'expected_close' => $session->expected_closing_amount,
                ])
                ->event('opened')
                ->log('opened');
        });

        static::updated(function (CashSession $session): void {
            if ($session->wasChanged('status') && $session->status === 'closed') {
                activity('cash_session')
                    ->performedOn($session)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'pos' => $session->type,
                        'opening_amount' => $session->starting_amount,
                        'expected_close' => $session->expected_closing_amount,
                        'actual_close' => $session->closing_amount,
                        'difference' => $session->difference_amount,
                    ])
                    ->event('closed')
                    ->log('closed');
            }
        });
    }

    protected function businessDateReferenceTime(): CarbonInterface
    {
        return $this->normalizeBusinessDateTime($this->started_at ?? $this->created_at);
    }
}
