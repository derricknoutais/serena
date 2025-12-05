<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

use \Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
class CashSession extends Model
{

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'validated_at' => 'datetime',
        'starting_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'expected_closing_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
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
}
