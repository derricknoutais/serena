<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class PaymentAdjustmentService
{
    public function __construct(
        private readonly NightAuditLockService $lockService,
        private readonly BusinessDayService $businessDayService,
    ) {}

    public function voidPayment(Payment $payment, User $actor, ?string $reason = null): Payment
    {
        if ($payment->entry_type === Payment::ENTRY_TYPE_REFUND) {
            throw ValidationException::withMessages([
                'payment' => 'Impossible d’annuler un remboursement.',
            ]);
        }

        if ($payment->voided_at || $payment->trashed()) {
            throw ValidationException::withMessages([
                'payment' => 'Ce paiement est déjà annulé.',
            ]);
        }

        $refundsTotal = (float) $payment->refunds()->sum('amount');
        if (abs($refundsTotal) > 0) {
            throw ValidationException::withMessages([
                'payment' => 'Impossible d’annuler un paiement déjà remboursé.',
            ]);
        }

        $hotel = $this->resolveHotel($payment);
        $businessDate = $this->resolveBusinessDate($hotel, $payment->business_date, $payment->paid_at ?? $payment->created_at);
        $this->ensureBusinessDateOpen($hotel, $businessDate, $actor);

        $previousVoidedAt = $payment->voided_at;

        $payment->forceFill([
            'voided_at' => now(),
            'voided_by_user_id' => $actor->id,
            'void_reason' => $reason,
        ])->save();

        $payment->delete();

        $payment->folio?->recalculateTotals();

        activity('payment')
            ->performedOn($payment)
            ->causedBy($actor)
            ->withProperties([
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $payment->paymentMethod?->name,
                'business_date' => $businessDate->toDateString(),
                'reason' => $reason,
                'previous_voided_at' => $previousVoidedAt?->toDateTimeString(),
                'voided_at' => $payment->voided_at?->toDateTimeString(),
            ])
            ->event('payment.voided')
            ->log('payment.voided');

        return $payment->refresh();
    }

    public function refundPayment(
        Payment $payment,
        User $actor,
        float $amount,
        int $paymentMethodId,
        ?int $cashSessionId,
        ?string $reason = null,
        ?string $refundReference = null,
    ): Payment {
        if ($payment->entry_type === Payment::ENTRY_TYPE_REFUND) {
            throw ValidationException::withMessages([
                'payment' => 'Impossible de rembourser un remboursement.',
            ]);
        }

        if ($payment->voided_at || $payment->trashed()) {
            throw ValidationException::withMessages([
                'payment' => 'Ce paiement est annulé et ne peut pas être remboursé.',
            ]);
        }

        $originalAmount = abs((float) $payment->amount);
        $refundsTotal = abs((float) $payment->refunds()->sum('amount'));
        $remaining = max(0, $originalAmount - $refundsTotal);

        $requested = abs($amount);
        if ($requested <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant du remboursement doit être supérieur à 0.',
            ]);
        }

        if ($requested > $remaining && ! $actor->can('payments.override_refund_limit')) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant dépasse le restant remboursable.',
            ]);
        }

        $paymentMethod = PaymentMethod::query()
            ->where('tenant_id', $payment->tenant_id)
            ->where(function ($query) use ($payment): void {
                $query->whereNull('hotel_id')->orWhere('hotel_id', $payment->hotel_id);
            })
            ->findOrFail($paymentMethodId);

        $resolvedCashSessionId = null;

        if ($paymentMethod->type === 'cash') {
            $resolvedCashSessionId = $this->resolveCashSessionId($payment, $cashSessionId);
        }

        $hotel = $this->resolveHotel($payment);
        $paidAt = now();
        $businessDate = $this->resolveBusinessDate($hotel, null, $paidAt);
        $this->ensureBusinessDateOpen($hotel, $businessDate, $actor);

        $folio = $payment->folio ?? $payment->folio()->first();

        if (! $folio) {
            throw ValidationException::withMessages([
                'payment' => 'Impossible de trouver le folio lié.',
            ]);
        }

        $refund = $folio->addPayment([
            'amount' => -$requested,
            'currency' => $payment->currency,
            'payment_method_id' => $paymentMethod->id,
            'paid_at' => $paidAt,
            'reference' => null,
            'notes' => $reason,
            'created_by_user_id' => $actor->id,
            'cash_session_id' => $resolvedCashSessionId,
            'parent_payment_id' => $payment->id,
            'entry_type' => Payment::ENTRY_TYPE_REFUND,
            'refund_reason' => $reason,
            'refund_reference' => $refundReference,
        ]);

        activity('payment')
            ->performedOn($refund)
            ->causedBy($actor)
            ->withProperties([
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'refund_amount' => $refund->amount,
                'method' => $paymentMethod->name,
                'business_date' => $businessDate->toDateString(),
                'reason' => $reason,
                'remaining_refundable' => max(0, $remaining - $requested),
            ])
            ->event('payment.refunded')
            ->log('payment.refunded');

        return $refund;
    }

    private function resolveCashSessionId(Payment $payment, ?int $cashSessionId): int
    {
        if ($cashSessionId) {
            $session = CashSession::query()
                ->whereKey($cashSessionId)
                ->where('tenant_id', $payment->tenant_id)
                ->where('hotel_id', $payment->hotel_id)
                ->where('status', 'open')
                ->first();

            if ($session) {
                return $session->id;
            }
        }

        $session = CashSession::query()
            ->where('tenant_id', $payment->tenant_id)
            ->where('hotel_id', $payment->hotel_id)
            ->where('type', 'frontdesk')
            ->where('status', 'open')
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'cash_session_id' => 'Aucune caisse réception ouverte. Veuillez ouvrir une session de caisse.',
            ]);
        }

        return $session->id;
    }

    private function resolveHotel(Payment $payment): Hotel
    {
        return $payment->hotel ?? Hotel::query()->findOrFail($payment->hotel_id);
    }

    private function resolveBusinessDate(
        Hotel $hotel,
        ?CarbonInterface $businessDate,
        ?CarbonInterface $reference,
    ): CarbonInterface {
        if ($businessDate) {
            return $businessDate;
        }

        return $this->businessDayService->resolveBusinessDate($hotel, $reference ?? now());
    }

    private function ensureBusinessDateOpen(Hotel $hotel, CarbonInterface $businessDate, User $actor): void
    {
        if (! $this->lockService->isClosed($hotel, $businessDate)) {
            return;
        }

        if ($actor->can('payments.override_closed_day')) {
            return;
        }

        abort(423, 'La journée d’affaires est verrouillée.');
    }
}
