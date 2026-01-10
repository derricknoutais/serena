<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Hotel;
use App\Models\Payment;
use App\Services\BusinessDayService;
use App\Services\FolioPayloadService;
use App\Services\NightAuditLockService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FolioController extends Controller
{
    public function __construct(
        private readonly FolioPayloadService $folioPayloads,
        private readonly NightAuditLockService $lockService,
        private readonly BusinessDayService $businessDayService,
    ) {}

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

        $data = $request->validate($this->chargeValidationRules());

        $hotel = $this->hotelForFolio($folio);
        $targetDate = $data['date'] ?? $item->date?->toDateString() ?? now()->toDateString();
        $businessDate = $this->resolveBusinessDateForCharge($hotel, $targetDate);
        $this->ensureBusinessDayOpen($hotel, $businessDate, $request);

        $hotel = $this->hotelForFolio($folio);
        $targetDate = $data['date'] ?? $item->date?->toDateString() ?? now()->toDateString();
        $businessDate = $this->resolveBusinessDateForCharge($hotel, $targetDate);
        $this->ensureBusinessDayOpen($hotel, $businessDate, $request);

        $hotel = $this->hotelForFolio($folio);
        $chargeDate = $data['date'] ?? now()->toDateString();
        $businessDate = $this->resolveBusinessDateForCharge($hotel, $chargeDate);
        $this->ensureBusinessDayOpen($hotel, $businessDate, $request);

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

        if ($request->wantsJson()) {
            return response()->json($this->chargeResponsePayload($folio, 'Charge ajoutée au folio.'));
        }

        return back()->with('success', 'Charge ajoutée au folio.');
    }

    public function updateItem(Request $request, Folio $folio, FolioItem $item): JsonResponse
    {
        Gate::authorize('folio_items.edit');

        $this->authorizeFolio($request, $folio);

        abort_if((int) $item->folio_id !== (int) $folio->id, 404);

        $data = $request->validate($this->chargeValidationRules());

        $item->fill([
            'description' => $data['description'],
            'quantity' => (float) $data['quantity'],
            'unit_price' => (float) $data['unit_price'],
            'tax_amount' => (float) ($data['tax_amount'] ?? 0),
            'discount_percent' => (float) ($data['discount_percent'] ?? 0),
            'discount_amount' => (float) ($data['discount_amount'] ?? 0),
            'date' => $data['date'] ?? $item->date?->toDateString(),
        ]);

        $item->recalculateAmounts();
        $item->save();

        $folio->recalculateTotals();

        return response()->json($this->chargeResponsePayload($folio, 'Charge mise à jour.'));
    }

    public function destroyItem(Request $request, Folio $folio, FolioItem $item): JsonResponse
    {
        Gate::authorize('folio_items.delete');

        $this->authorizeFolio($request, $folio);

        $hotel = $this->hotelForFolio($folio);
        $businessDate = Carbon::parse($item->business_date ?? $item->date ?? now()->toDateString());
        $this->ensureBusinessDayOpen($hotel, $businessDate, $request);

        abort_if((int) $item->folio_id !== (int) $folio->id, 404);

        $item->delete();
        $folio->recalculateTotals();

        return response()->json($this->chargeResponsePayload($folio, 'Charge supprimée.'));
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

        $hotel = $this->hotelForFolio($folio);
        $paidAt = Carbon::parse($data['paid_at'] ?? now());
        $businessDate = $this->resolveBusinessDateForPayment($hotel, $paidAt);
        $this->ensureBusinessDayOpen($hotel, $businessDate, $request);

        $folio->addPayment([
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? $folio->currency,
            'payment_method_id' => $data['payment_method_id'],
            'paid_at' => $paidAt,
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

    public function updatePayment(Request $request, Folio $folio, Payment $payment): JsonResponse
    {
        Gate::authorize('payments.edit');

        $this->authorizeFolio($request, $folio);

        abort_if((int) $payment->folio_id !== (int) $folio->id, 404);

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

        $hotel = $this->hotelForFolio($folio);
        $paidAt = Carbon::parse($data['paid_at'] ?? $payment->paid_at ?? now());
        $businessDate = $this->resolveBusinessDateForPayment($hotel, $paidAt);
        $this->ensureBusinessDayOpen($hotel, $businessDate, $request);

        $payment->forceFill([
            'amount' => (float) $data['amount'],
            'currency' => $data['currency'] ?? $folio->currency,
            'payment_method_id' => $data['payment_method_id'],
            'paid_at' => $paidAt,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['note'] ?? $data['notes'] ?? null,
            'cash_session_id' => $cashSessionId,
        ])->save();

        $folio->recalculateTotals();
        $folio->refresh()->loadMissing('payments.paymentMethod');

        return response()->json($this->paymentResponsePayload($folio, 'Paiement mis à jour.'));
    }

    public function destroyPayment(Request $request, Folio $folio, Payment $payment): RedirectResponse|JsonResponse
    {
        abort_unless(
            $request->user()->can('payments.delete') || $request->user()->can('folio_items.void'),
            403
        );

        $hotel = $this->hotelForFolio($folio);
        $businessDate = Carbon::parse($payment->business_date ?? now()->toDateString());
        $this->ensureBusinessDayOpen($hotel, $businessDate, $request);

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

    private function chargeResponsePayload(Folio $folio, string $message): array
    {
        return [
            'message' => $message,
            'totals' => [
                'charges' => $folio->charges_total,
                'payments' => $folio->payments_total,
                'balance' => $folio->balance,
            ],
        ];
    }

    private function chargeValidationRules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
        ];
    }

    private function hotelForFolio(Folio $folio): Hotel
    {
        return $folio->hotel ?? Hotel::query()->findOrFail($folio->hotel_id);
    }

    private function resolveBusinessDateForCharge(Hotel $hotel, string $date): CarbonInterface
    {
        return $this->businessDayService->resolveBusinessDate($hotel, Carbon::parse($date));
    }

    private function resolveBusinessDateForPayment(Hotel $hotel, Carbon $reference): CarbonInterface
    {
        return $this->businessDayService->resolveBusinessDate($hotel, $reference);
    }

    private function ensureBusinessDayOpen(Hotel $hotel, CarbonInterface $businessDate, Request $request): void
    {
        $override = $request->boolean('override_business_day');

        $this->lockService->assertBusinessDateOpen($hotel, $businessDate, $request->user(), $override);
    }
}
