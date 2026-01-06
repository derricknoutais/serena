<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use App\Models\Payment;
use App\Services\FolioPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FolioController extends Controller
{
    public function __construct(private readonly FolioPayloadService $folioPayloads) {}

    public function show(Request $request, Folio $folio): Response|JsonResponse
    {
        $this->authorizeFolio($request, $folio);

        $payload = $this->folioPayloads->make($folio, null, $request->user());

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Frontdesk/Folios/Show', $payload);
    }

    public function storeItem(Request $request, Folio $folio): RedirectResponse
    {
        $this->authorizeFolio($request, $folio);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
        ]);

        $quantity = (float) $data['quantity'];
        $unitPrice = (float) $data['unit_price'];
        $taxAmount = isset($data['tax_amount']) ? (float) $data['tax_amount'] : 0;
        $discountPercent = isset($data['discount_percent']) ? (float) $data['discount_percent'] : 0.0;
        $discountAmount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : 0.0;

        $folio->addCharge([
            'description' => $data['description'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_amount' => $taxAmount,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'date' => $data['date'] ?? now()->toDateString(),
            'account_code' => $data['account_code'] ?? null,
            'type' => $data['type'] ?? null,
            'product_id' => $data['product_id'] ?? null,
        ]);

        return back()->with('success', 'Charge ajoutée au folio.');
    }

    public function storePayment(Request $request, Folio $folio): RedirectResponse|JsonResponse
    {
        Gate::authorize('payments.create');

        $this->authorizeFolio($request, $folio);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')
                    ->where('tenant_id', $folio->tenant_id)
                    ->where(function ($query) use ($folio) {
                        $query->whereNull('hotel_id')->orWhere('hotel_id', $folio->hotel_id);
                    }),
            ],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentMethod = \App\Models\PaymentMethod::where('tenant_id', $folio->tenant_id)->findOrFail($data['payment_method_id']);
        $cashSessionId = null;

        if ($paymentMethod->type === 'cash') {
            $activeSession = \App\Models\CashSession::query()
                ->where('tenant_id', $folio->tenant_id)
                ->where('hotel_id', $folio->hotel_id)
                ->where('type', 'frontdesk')
                ->where('status', 'open')
                ->first();

            if (! $activeSession) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payment_method_id' => 'Aucune caisse réception ouverte. Veuillez ouvrir une session de caisse.',
                ]);
            }
            $cashSessionId = $activeSession->id;
        }

        $folio->addPayment([
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? $folio->currency,
            'payment_method_id' => $data['payment_method_id'],
            'paid_at' => $data['paid_at'] ?? now(),
            'reference' => $data['reference'] ?? null,
            'notes' => $data['note'] ?? $data['notes'] ?? null,
            'created_by_user_id' => $request->user()->id,
            'cash_session_id' => $cashSessionId,
        ]);

        if (! $request->wantsJson()) {
            return back()->with('success', 'Paiement enregistré.');
        }

        $folio->refresh()->loadMissing('payments.paymentMethod');

        return response()->json($this->paymentResponsePayload($folio));
    }

    public function destroyPayment(Request $request, Folio $folio, Payment $payment): RedirectResponse|JsonResponse
    {
        Gate::authorize('folio_items.void');

        $this->authorizeFolio($request, $folio);

        abort_if((int) $payment->folio_id !== (int) $folio->id, 404);

        $payment->delete();
        $folio->recalculateTotals();

        if (! $request->wantsJson()) {
            return back()->with('success', 'Paiement supprimé.');
        }

        $folio->refresh()->loadMissing('payments.paymentMethod');

        return response()->json($this->paymentResponsePayload($folio, 'Paiement supprimé.'));
    }

    private function authorizeFolio(Request $request, Folio $folio): void
    {
        abort_unless($folio->tenant_id === $request->user()->tenant_id, 404);
    }

    private function paymentResponsePayload(Folio $folio, string $message = 'Paiement enregistré.'): array
    {
        return [
            'message' => $message,
            'totals' => [
                'charges' => $folio->charges_total,
                'payments' => $folio->payments_total,
                'balance' => $folio->balance,
            ],
            'payments' => $folio->payments->map(function (Payment $payment) {
                $method = $payment->paymentMethod ? [
                    'id' => $payment->paymentMethod->id,
                    'name' => $payment->paymentMethod->name,
                ] : null;

                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'notes' => $payment->notes,
                    'method' => $method,
                    'payment_method' => $method,
                ];
            })->values(),
        ];
    }
}
