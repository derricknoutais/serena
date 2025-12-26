<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Services\FolioBillingService;
use App\Services\ReservationStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationStatusController extends Controller
{
    public function __construct(
        private readonly ReservationStateMachine $reservationStateMachine,
        private readonly FolioBillingService $billingService,
    ) {}

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        abort_unless($reservation->tenant_id === $tenantId, 404);

        $reservation->loadMissing('room');

        $validated = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'check_in', 'check_out', 'cancel', 'no_show'])],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'penalty_note' => ['nullable', 'string', 'max:255'],
            'actual_check_in_at' => ['nullable', 'date'],
            'actual_check_out_at' => ['nullable', 'date'],
            'early_fee_override' => ['nullable', 'numeric', 'min:0'],
            'late_fee_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $action = $validated['action'];

        $map = [
            'confirm' => Reservation::STATUS_CONFIRMED,
            'check_in' => Reservation::STATUS_IN_HOUSE,
            'check_out' => Reservation::STATUS_CHECKED_OUT,
            'cancel' => Reservation::STATUS_CANCELLED,
            'no_show' => Reservation::STATUS_NO_SHOW,
        ];

        $targetStatus = $map[$action];
        $canOverrideFees = $request->user()?->hasAnyRole(['owner', 'manager']) ?? false;
        $actualCheckInAt = ! empty($validated['actual_check_in_at'])
            ? Carbon::parse($validated['actual_check_in_at'])
            : null;
        $actualCheckOutAt = ! empty($validated['actual_check_out_at'])
            ? Carbon::parse($validated['actual_check_out_at'])
            : null;

        DB::transaction(function () use (
            $action,
            $reservation,
            $targetStatus,
            $validated,
            $canOverrideFees,
            $actualCheckInAt,
            $actualCheckOutAt
        ): void {
            $updatedReservation = match ($action) {
                'confirm' => $this->reservationStateMachine->confirm($reservation),
                'check_in' => $this->reservationStateMachine->checkIn(
                    $reservation,
                    $actualCheckInAt,
                    $canOverrideFees,
                    isset($validated['early_fee_override']) ? (float) $validated['early_fee_override'] : null,
                ),
                'check_out' => $this->reservationStateMachine->checkOut(
                    $reservation,
                    $actualCheckOutAt,
                    $canOverrideFees,
                    isset($validated['late_fee_override']) ? (float) $validated['late_fee_override'] : null,
                ),
                'cancel' => $this->reservationStateMachine->cancel($reservation),
                'no_show' => $this->reservationStateMachine->markNoShow($reservation),
                default => throw ValidationException::withMessages([
                    'status' => 'Action non supportée.',
                ]),
            };

            $this->applyPenalty(
                $updatedReservation,
                $targetStatus,
                (float) ($validated['penalty_amount'] ?? 0),
                $validated['penalty_note'] ?? null
            );
        });

        return back()->with('success', 'Statut mis à jour.');
    }

    public function preview(Request $request, Reservation $reservation): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        abort_unless($reservation->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'action' => ['required', Rule::in(['check_in', 'check_out'])],
            'actual_datetime' => ['required', 'date'],
        ]);

        $canOverrideFees = $request->user()?->hasAnyRole(['owner', 'manager']) ?? false;
        $actual = Carbon::parse($data['actual_datetime']);

        $decision = $this->reservationStateMachine->getStayAdjustmentService()->evaluateEarlyLate(
            $reservation,
            $data['action'] === 'check_in'
                ? $actual
                : ($reservation->actual_check_in_at ?? ($reservation->check_in_date ? Carbon::parse($reservation->check_in_date) : $actual)),
            $data['action'] === 'check_out' ? $actual : null,
        );

        if ($data['action'] === 'check_in' && $decision['early_blocked'] && ! $canOverrideFees) {
            throw ValidationException::withMessages([
                'action' => $decision['early_reason'] ?? 'Arrivée anticipée non autorisée.',
            ]);
        }

        if ($decision['late_blocked'] && ! $canOverrideFees) {
            throw ValidationException::withMessages([
                'action' => $decision['late_reason'] ?? 'Départ tardif non autorisé.',
            ]);
        }

        return response()->json([
            'early' => [
                'is_early_checkin' => $decision['is_early_checkin'],
                'fee' => $decision['early_fee_amount'],
                'reason' => $decision['early_reason'],
                'policy' => $decision['early_policy'],
                'blocked' => $decision['early_blocked'],
            ],
            'late' => [
                'is_late_checkout' => $decision['is_late_checkout'],
                'fee' => $decision['late_fee_amount'],
                'reason' => $decision['late_reason'],
                'policy' => $decision['late_policy'],
                'blocked' => $decision['late_blocked'],
                'fee_type' => $decision['late_fee_type'],
                'fee_value' => $decision['late_fee_value'],
                'minutes' => $decision['late_minutes'],
                'grace_minutes' => $decision['late_grace_minutes'],
                'expected_checkout_at' => $decision['late_expected_at'],
                'actual_checkout_at' => $data['action'] === 'check_out' ? $actual->toDateTimeString() : null,
            ],
            'currency' => $decision['currency'],
        ]);
    }

    private function applyPenalty(Reservation $reservation, string $targetStatus, float $amount, ?string $note): void
    {
        if ($amount <= 0 || ! in_array($targetStatus, [Reservation::STATUS_CANCELLED, Reservation::STATUS_NO_SHOW], true)) {
            return;
        }

        $folio = $this->billingService->ensureMainFolioForReservation($reservation);
        $description = $targetStatus === Reservation::STATUS_CANCELLED ? 'Frais d’annulation' : 'Frais de no-show';

        $folio->addCharge([
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'tax_amount' => 0,
            'type' => 'penalty',
            'meta' => [
                'penalty_note' => $note,
            ],
        ]);

        $user = request()->user();

        if (! $user) {
            return;
        }

        $paymentMethod = PaymentMethod::query()
            ->where('tenant_id', $reservation->tenant_id)
            ->where(function ($query) use ($reservation): void {
                $query->whereNull('hotel_id')->orWhere('hotel_id', $reservation->hotel_id);
            })
            ->where('type', 'cash')
            ->first();

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'penalty_amount' => 'Aucun mode de paiement cash disponible.',
            ]);
        }

        $activeSession = CashSession::query()
            ->where('tenant_id', $reservation->tenant_id)
            ->where('hotel_id', $reservation->hotel_id)
            ->where('type', 'frontdesk')
            ->where('status', 'open')
            ->first();

        if (! $activeSession) {
            throw ValidationException::withMessages([
                'penalty_amount' => 'Aucune caisse réception ouverte. Veuillez ouvrir une session de caisse.',
            ]);
        }
    }
}
