<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\FolioBillingService;
use App\Services\ReservationStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $reservation = match ($action) {
            'confirm' => $this->reservationStateMachine->confirm($reservation),
            'check_in' => $this->reservationStateMachine->checkIn($reservation),
            'check_out' => $this->reservationStateMachine->checkOut($reservation),
            'cancel' => $this->reservationStateMachine->cancel($reservation),
            'no_show' => $this->reservationStateMachine->markNoShow($reservation),
            default => throw ValidationException::withMessages([
                'status' => 'Action non supportée.',
            ]),
        };

        $this->applyPenalty($reservation, $targetStatus, (float) ($validated['penalty_amount'] ?? 0), $validated['penalty_note'] ?? null);

        return back()->with('success', 'Statut mis à jour.');
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
    }
}
