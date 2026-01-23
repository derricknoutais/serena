<?php

namespace App\Http\Controllers;

use App\Http\Requests\RefundPaymentRequest;
use App\Http\Requests\VoidPaymentRequest;
use App\Models\Payment;
use App\Services\FolioPayloadService;
use App\Services\PaymentAdjustmentService;
use App\Services\VapidEventNotifier;
use Illuminate\Http\JsonResponse;

class PaymentAdjustmentController extends Controller
{
    public function void(
        VoidPaymentRequest $request,
        Payment $payment,
        PaymentAdjustmentService $service,
        FolioPayloadService $payloads,
        VapidEventNotifier $vapidEventNotifier,
    ): JsonResponse {
        $this->authorize('void', $payment);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validated();

        $service->voidPayment($payment, $user, $data['reason'] ?? null);

        $folio = $payment->folio ?? $payment->folio()->firstOrFail();

        $paymentUrl = $folio->reservation_id
            ? route('reservations.folio.show', ['reservation' => $folio->reservation_id])
            : route('folios.show', ['folio' => $folio->id]);
        $vapidEventNotifier->notifyOwnersAndManagers(
            eventKey: 'payment.voided',
            tenantId: (string) $folio->tenant_id,
            hotelId: $folio->hotel_id,
            title: 'Paiement annulé',
            body: 'Un paiement a été annulé.',
            url: $paymentUrl,
            tag: 'payment-voided',
        );

        return response()->json($payloads->make($folio, $folio->reservation, $user));
    }

    public function refund(
        RefundPaymentRequest $request,
        Payment $payment,
        PaymentAdjustmentService $service,
        FolioPayloadService $payloads,
        VapidEventNotifier $vapidEventNotifier,
    ): JsonResponse {
        $this->authorize('refund', $payment);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validated();

        $service->refundPayment(
            $payment,
            $user,
            (float) $data['amount'],
            (int) $data['payment_method_id'],
            $data['cash_session_id'] ?? null,
            $data['reason'] ?? null,
            $data['refund_reference'] ?? null,
        );

        $folio = $payment->folio ?? $payment->folio()->firstOrFail();

        $amountLabel = number_format((float) $data['amount'], 0, ',', ' ');
        $paymentUrl = $folio->reservation_id
            ? route('reservations.folio.show', ['reservation' => $folio->reservation_id])
            : route('folios.show', ['folio' => $folio->id]);
        $vapidEventNotifier->notifyOwnersAndManagers(
            eventKey: 'payment.refunded',
            tenantId: (string) $folio->tenant_id,
            hotelId: $folio->hotel_id,
            title: 'Paiement remboursé',
            body: sprintf('Remboursement de %s %s effectué.', $amountLabel, $folio->currency ?? 'XAF'),
            url: $paymentUrl,
            tag: 'payment-refunded',
        );

        return response()->json($payloads->make($folio, $folio->reservation, $user));
    }
}
