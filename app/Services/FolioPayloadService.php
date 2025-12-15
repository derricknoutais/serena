<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\User;

class FolioPayloadService
{
    /**
     * Build a structured payload for folio driven UIs.
     */
    public function make(Folio $folio, ?Reservation $reservation = null, ?User $user = null): array
    {
        $folio->loadMissing([
            'items',
            'itemsWithTrashed',
            'payments.paymentMethod',
            'invoices',
            'reservation.guest',
        ]);

        $reservationModel = $reservation ?? $folio->reservation;

        $paymentMethods = PaymentMethod::query()
            ->where('tenant_id', $folio->tenant_id)
            ->where(function ($query) use ($folio) {
                $query->whereNull('hotel_id')->orWhere('hotel_id', $folio->hotel_id);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type'])
            ->values();

        return [
            'folio' => [
                'id' => $folio->id,
                'code' => $folio->code,
                'currency' => $folio->currency,
                'is_main' => (bool) $folio->is_main,
                'status' => $folio->status,
                'opened_at' => $folio->opened_at?->toDateTimeString(),
                'closed_at' => $folio->closed_at?->toDateTimeString(),
                'charges_total' => $folio->charges_total,
                'payments_total' => $folio->payments_total,
                'balance' => $folio->balance,
            ],
            'reservation' => $reservationModel ? [
                'id' => $reservationModel->id,
                'code' => $reservationModel->code,
                'status' => $reservationModel->status,
                'status_label' => $reservationModel->status_label,
                'check_in_date' => $reservationModel->check_in_date?->toDateString(),
                'check_out_date' => $reservationModel->check_out_date?->toDateString(),
                'guest' => $reservationModel->guest ? [
                    'id' => $reservationModel->guest->id,
                    'name' => $reservationModel->guest->full_name,
                ] : null,
            ] : null,
            'items' => ($folio->itemsWithTrashed ?? $folio->items)->map(fn ($item) => [
                'id' => $item->id,
                'date' => $item->date?->toDateString(),
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'discount_amount' => $item->discount_amount,
                'net_amount' => $item->net_amount,
                'base_amount' => $item->base_amount,
                'tax_amount' => $item->tax_amount,
                'total_amount' => $item->total_amount,
                'created_at' => $item->created_at?->toDateTimeString(),
                'deleted_at' => $item->deleted_at?->toDateTimeString(),
            ])->values(),
            'payments' => $folio->payments->map(function ($payment) {
                $method = $payment->paymentMethod ? [
                    'id' => $payment->paymentMethod->id,
                    'name' => $payment->paymentMethod->name,
                    'code' => $payment->paymentMethod->code,
                ] : null;

                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'reference' => $payment->reference,
                    'notes' => $payment->notes,
                    'method' => $method,
                    'payment_method' => $method,
                ];
            })->values(),
            'invoices' => $folio->invoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'total_amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
            ])->values(),
            'paymentMethods' => $paymentMethods,
            'permissions' => [
                'can_manage_payments' => $user?->can('folio_items.void') ?? false,
                'can_manage_invoices' => $user?->can('invoices.create') ?? false,
            ],
        ];
    }
}
