<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use App\Models\Hotel;
use App\Models\Invoice;
use App\Services\BusinessDayService;
use App\Services\FolioBillingService;
use App\Services\NightAuditLockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly NightAuditLockService $lockService,
        private readonly BusinessDayService $businessDayService,
    ) {}

    public function storeFromFolio(Request $request, Folio $folio, FolioBillingService $billingService): RedirectResponse|JsonResponse
    {
        $this->authorize('invoices.create');
        $this->authorizeFolio($request, $folio);

        $existing = $folio->invoices()->latest()->first();
        $regenerate = $request->boolean('regenerate');

        if ($existing && ! $regenerate) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Facture déjà générée.',
                    'invoice' => $this->invoicePayload($existing),
                ]);
            }

            return back()->with('info', 'Une facture existe déjà pour ce folio.');
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
            'close_folio' => ['nullable', 'boolean'],
            'regenerate' => ['nullable', 'boolean'],
        ]);

        $hotel = $this->hotelForFolio($folio);
        $businessDate = $this->businessDayService->resolveBusinessDate($hotel, Carbon::now());
        $this->lockService->assertBusinessDateOpen($hotel, $businessDate, $request->user(), $request->boolean('override_business_day'));

        $invoice = $existing && $regenerate
            ? $billingService->regenerateInvoiceFromFolio($folio, $existing, [
                'notes' => $data['notes'] ?? null,
                'close_folio' => (bool) ($data['close_folio'] ?? false),
                'user_id' => $request->user()->id,
            ])
            : $billingService->generateInvoiceFromFolio($folio, [
                'notes' => $data['notes'] ?? null,
                'close_folio' => (bool) ($data['close_folio'] ?? false),
                'user_id' => $request->user()->id,
            ]);

        if (! $request->wantsJson()) {
            return back()->with('success', $existing && $regenerate
                ? 'Facture régénérée : '.$invoice->number
                : 'Facture générée : '.$invoice->number);
        }

        return response()->json([
            'message' => $existing && $regenerate ? 'Facture régénérée.' : 'Facture générée.',
            'invoice' => $this->invoicePayload($invoice),
        ]);
    }

    public function pdf(Request $request, Invoice $invoice): View
    {
        $this->authorize('invoices.view');
        $this->authorizeInvoice($request, $invoice);

        $invoice->loadMissing(['items', 'folio.hotel', 'folio.reservation.guest', 'guest']);

        return view('invoices.pdf', [
            'invoice' => $invoice,
            'hotel' => $invoice->folio?->hotel,
            'guest' => $invoice->guest ?? $invoice->folio?->reservation?->guest,
            'items' => $invoice->items,
            'format' => $request->query('format', 'standard'),
        ]);
    }

    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'total_amount' => $invoice->total_amount,
            'currency' => $invoice->currency,
        ];
    }

    private function authorizeFolio(Request $request, Folio $folio): void
    {
        abort_unless($folio->tenant_id === $request->user()->tenant_id, 404);
    }

    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->tenant_id === $request->user()->tenant_id, 404);
    }

    private function hotelForFolio(Folio $folio): Hotel
    {
        return $folio->hotel ?? Hotel::query()->findOrFail($folio->hotel_id);
    }
}
