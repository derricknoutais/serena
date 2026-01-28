<?php

namespace App\Models;

use App\Services\LoyaltyEarningService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Folio extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'reservation_id',
        'guest_id',
        'code',
        'status',
        'is_main',
        'type',
        'origin',
        'currency',
        'billing_name',
        'opened_at',
        'closed_at',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'total_charges',
        'total_payments',
        'balance',
        'charges_total',
        'payments_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'float',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FolioItem::class);
    }

    public function itemsWithTrashed(): HasMany
    {
        return $this->items()->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function canEditItems(): bool
    {
        if ($this->relationLoaded('invoices')) {
            return ! $this->invoices->contains(fn (Invoice $invoice) => $invoice->status === Invoice::STATUS_ISSUED);
        }

        return ! $this->invoices()
            ->where('status', Invoice::STATUS_ISSUED)
            ->exists();
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function getTotalChargesAttribute(): float
    {
        if ($this->relationLoaded('items')) {
            return (float) $this->items->sum('total_amount');
        }

        return (float) $this->items()->sum('total_amount');
    }

    public function getChargesTotalAttribute(): float
    {
        return $this->total_charges;
    }

    public function getTotalPaymentsAttribute(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments
                ->whereNull('deleted_at')
                ->sum('amount');
        }

        return (float) $this->payments()->sum('amount');
    }

    public function getPaymentsTotalAttribute(): float
    {
        return $this->total_payments;
    }

    public function getBalanceAttribute(?string $value): float
    {
        if ($value !== null) {
            return (float) $value;
        }

        return $this->total_charges - $this->total_payments;
    }

    public function addCharge(array $data): FolioItem
    {
        if ($this->isClosed()) {
            throw new LogicException('Cannot add charges to a closed folio.');
        }

        $quantity = (float) ($data['quantity'] ?? 1);
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $baseAmount = $quantity * $unitPrice;
        $taxAmount = (float) ($data['tax_amount'] ?? 0);

        $payload = $data;
        $payload['tenant_id'] = $this->tenant_id;
        $payload['hotel_id'] = $this->hotel_id;
        $payload['date'] = $data['date'] ?? now()->toDateString();
        $payload['quantity'] = $quantity;
        $payload['unit_price'] = $unitPrice;
        $payload['base_amount'] = $baseAmount;
        $payload['tax_amount'] = $taxAmount;
        $payload['discount_percent'] = (float) ($data['discount_percent'] ?? 0);
        $payload['discount_amount'] = (float) ($data['discount_amount'] ?? 0);

        $item = $this->items()->create($payload);
        $item->recalculateAmounts();
        $item->save();

        return tap($item, function (FolioItem $created): void {
            $this->recalculateTotals();

            activity('folio')
                ->performedOn($this)
                ->causedBy(auth()->user())
                ->withProperties([
                    'action' => 'item_created',
                    'item_id' => $created->id,
                    'item_type' => $created->type ?? null,
                    'description' => $created->description,
                    'amount' => $created->total_amount,
                    'currency' => $this->currency,
                ])
                ->event('item_created')
                ->log('item_created');
        });
    }

    public function addPayment(array $data): Payment
    {
        if ($this->isClosed()) {
            throw new LogicException('Cannot add payments to a closed folio.');
        }
        $reservation = $this->reservation;
        if ($reservation?->status === Reservation::STATUS_CANCELLED) {
            throw new LogicException('Cannot add payments to a cancelled reservation.');
        }
        if ($reservation?->status === Reservation::STATUS_NO_SHOW) {
            throw new LogicException('Cannot add payments to a no show reservation.');
        }

        $payload = $data;
        $payload['tenant_id'] = $this->tenant_id;
        $payload['hotel_id'] = $this->hotel_id;
        $payload['currency'] = $data['currency'] ?? $this->currency;
        $payload['paid_at'] = $data['paid_at'] ?? now();
        $payload['entry_type'] = $data['entry_type'] ?? Payment::ENTRY_TYPE_PAYMENT;

        $payment = $this->payments()->create($payload);

        return tap($payment, function (Payment $created): void {
            $this->recalculateTotals();

            activity('payment')
                ->performedOn($created)
                ->causedBy(auth()->user())
                ->withProperties([
                    'amount' => $created->amount,
                    'currency' => $created->currency,
                    'method' => $created->paymentMethod?->name,
                    'cash_session_id' => $created->cash_session_id,
                ])
                ->event('created')
                ->log('created');

            if ($created->entry_type === Payment::ENTRY_TYPE_PAYMENT && $created->amount > 0) {
                $reservation = $this->relationLoaded('reservation')
                    ? $this->reservation
                    : $this->reservation()->first();

                if ($reservation) {
                    app(LoyaltyEarningService::class)->recordPointsForReservation($reservation);
                }
            }
        });
    }

    public function recalculateTotals(): void
    {
        $charges = (float) $this->items()->sum('total_amount');
        $payments = (float) $this->payments()->sum('amount');

        $this->forceFill([
            'balance' => $charges - $payments,
        ])->save();
    }

    public function close(): void
    {
        if ($this->isClosed()) {
            return;
        }

        $this->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
        ])->save();
    }
}
