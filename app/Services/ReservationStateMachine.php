<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Validation\ValidationException;

class ReservationStateMachine
{
    public function __construct(
        private readonly ReservationAvailabilityService $availability,
        private readonly FolioBillingService $billing,
        private readonly RoomStateMachine $roomStateMachine,
    ) {}

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, Reservation::allowedStatusTransitions()[$from] ?? [], true);
    }

    public function confirm(Reservation $reservation): Reservation
    {
        $this->assertTransition($reservation, Reservation::STATUS_CONFIRMED);
        $this->ensureAvailability($reservation, Reservation::STATUS_CONFIRMED);

        return $this->applyStatus($reservation, Reservation::STATUS_CONFIRMED);
    }

    public function checkIn(Reservation $reservation): Reservation
    {
        $this->assertTransition($reservation, Reservation::STATUS_IN_HOUSE);
        $this->ensureAvailability($reservation, Reservation::STATUS_IN_HOUSE);

        if (! $reservation->room_id) {
            throw ValidationException::withMessages([
                'room_id' => 'Une chambre doit être assignée avant le check-in.',
            ]);
        }

        $reservation->loadMissing('room');

        if (! $reservation->room instanceof Room) {
            throw ValidationException::withMessages([
                'room_id' => 'La chambre sélectionnée est invalide.',
            ]);
        }

        $this->roomStateMachine->markOccupied($reservation->room, $reservation);
        $reservation->actual_check_in_at = now();
        $this->billing->ensureMainFolioForReservation($reservation);
        $this->billing->syncStayChargeFromReservation($reservation);

        return $this->applyStatus($reservation, Reservation::STATUS_IN_HOUSE);
    }

    public function checkOut(Reservation $reservation): Reservation
    {
        $this->assertTransition($reservation, Reservation::STATUS_CHECKED_OUT);

        $reservation->loadMissing('room');

        if ($reservation->room) {
            $this->roomStateMachine->markAvailable($reservation->room);
            $reservation->room->hk_status = 'dirty';
            $reservation->room->save();
        }

        $reservation->actual_check_out_at = now();

        return $this->applyStatus($reservation, Reservation::STATUS_CHECKED_OUT);
    }

    public function cancel(Reservation $reservation): Reservation
    {
        $this->assertTransition($reservation, Reservation::STATUS_CANCELLED);

        if ($reservation->room_id) {
            $reservation->loadMissing('room');
            if ($reservation->room) {
                $this->roomStateMachine->markAvailable($reservation->room);
            }
        }

        return $this->applyStatus($reservation, Reservation::STATUS_CANCELLED);
    }

    public function markNoShow(Reservation $reservation): Reservation
    {
        $this->assertTransition($reservation, Reservation::STATUS_NO_SHOW);

        if ($reservation->check_in_date && now()->lt($reservation->check_in_date)) {
            throw ValidationException::withMessages([
                'status' => 'Impossible de marquer un no-show avant la date d’arrivée.',
            ]);
        }

        if ($reservation->room_id) {
            $reservation->loadMissing('room');
            if ($reservation->room) {
                $this->roomStateMachine->markAvailable($reservation->room);
            }
        }

        return $this->applyStatus($reservation, Reservation::STATUS_NO_SHOW);
    }

    private function ensureAvailability(Reservation $reservation, string $status): void
    {
        $this->availability->ensureAvailable([
            'tenant_id' => $reservation->tenant_id,
            'hotel_id' => $reservation->hotel_id,
            'room_type_id' => $reservation->room_type_id,
            'room_id' => $reservation->room_id,
            'check_in_date' => $reservation->check_in_date?->toDateString(),
            'check_out_date' => $reservation->check_out_date?->toDateString(),
            'status' => $status,
        ], $reservation->id);
    }

    private function applyStatus(Reservation $reservation, string $status): Reservation
    {
        $reservation->status = $status;
        $reservation->save();

        return $reservation;
    }

    private function assertTransition(Reservation $reservation, string $targetStatus): void
    {
        if (! $this->canTransition($reservation->status, $targetStatus)) {
            throw ValidationException::withMessages([
                'status' => 'Transition de statut non autorisée.',
            ]);
        }
    }
}
