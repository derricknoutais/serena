<?php

namespace App\Http\Controllers;

use App\Http\Requests\RefundPaymentRequest;
use App\Http\Requests\VoidPaymentRequest;
use App\Models\Payment;
use App\Services\FolioPayloadService;
use App\Services\PaymentAdjustmentService;
use Illuminate\Http\JsonResponse;

class PaymentAdjustmentController extends Controller
{
    public function void(
        VoidPaymentRequest $request,
        Payment $payment,
        PaymentAdjustmentService $service,
        FolioPayloadService $payloads,
    ): JsonResponse {
        $this->authorize('void', $payment);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validated();

        $service->voidPayment($payment, $user, $data['reason'] ?? null);

        $folio = $payment->folio ?? $payment->folio()->firstOrFail();

        return response()->json($payloads->make($folio, $folio->reservation, $user));
    }

    public function refund(
        RefundPaymentRequest $request,
        Payment $payment,
        PaymentAdjustmentService $service,
        FolioPayloadService $payloads,
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

        return response()->json($payloads->make($folio, $folio->reservation, $user));
    }
}
