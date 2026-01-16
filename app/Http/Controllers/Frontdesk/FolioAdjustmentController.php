<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFolioAdjustmentRequest;
use App\Models\Folio;
use App\Models\Hotel;
use App\Models\User;
use App\Services\BusinessDayService;
use App\Services\NightAuditLockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class FolioAdjustmentController extends Controller
{
    public function __construct(
        private readonly NightAuditLockService $lockService,
        private readonly BusinessDayService $businessDayService,
    ) {}

    public function store(StoreFolioAdjustmentRequest $request, Folio $folio): JsonResponse
    {
        $this->authorize('folios.adjust');

        /** @var User $user */
        $user = $request->user();

        $this->ensureFolioScope($folio, $user);

        if ($folio->isClosed()) {
            abort(422, 'Le folio est clôturé.');
        }

        $data = $request->validated();
        $amount = (float) $data['amount'];
        $reason = $data['reason'];
        $chargeDate = $data['date'] ?? now()->toDateString();

        $hotel = $this->hotelForFolio($folio);
        $businessDate = $this->businessDayService->resolveBusinessDate($hotel, Carbon::parse($chargeDate));
        $this->lockService->assertBusinessDateOpen($hotel, $businessDate, $user, $request->boolean('override_business_day'));

        $item = $folio->items()->create([
            'tenant_id' => $folio->tenant_id,
            'hotel_id' => $folio->hotel_id,
            'date' => $chargeDate,
            'business_date' => $businessDate->toDateString(),
            'description' => sprintf('Ajustement: %s', $reason),
            'type' => 'adjustment',
            'quantity' => 1,
            'unit_price' => $amount,
            'tax_amount' => 0,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'meta' => [
                'reason' => $reason,
            ],
        ]);

        $item->recalculateAmounts();
        $item->save();

        $folio->recalculateTotals();

        activity('folio')
            ->performedOn($folio)
            ->causedBy($user)
            ->withProperties([
                'action' => 'adjustment_added',
                'item_id' => $item->id,
                'amount' => $item->total_amount,
                'currency' => $folio->currency,
                'reason' => $reason,
                'folio_code' => $folio->code,
            ])
            ->event('adjustment_added')
            ->log('folio.adjustment_added');

        return response()->json($this->responsePayload($folio, 'Ajustement ajouté.'));
    }

    private function responsePayload(Folio $folio, string $message): array
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

    private function ensureFolioScope(Folio $folio, User $user): void
    {
        if ($folio->tenant_id !== $user->tenant_id) {
            abort(404);
        }

        if ((int) $folio->hotel_id !== (int) ($user->active_hotel_id ?? $user->hotel_id)) {
            abort(404);
        }
    }

    private function hotelForFolio(Folio $folio): Hotel
    {
        return $folio->hotel ?? Hotel::query()->findOrFail($folio->hotel_id);
    }
}
