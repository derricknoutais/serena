<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MaintenanceTicket;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReservationStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        Reservation::STATUS_PENDING => [
            Reservation::STATUS_CONFIRMED,
            Reservation::STATUS_CANCELLED,
            Reservation::STATUS_NO_SHOW,
        ],
        Reservation::STATUS_CONFIRMED => [
            Reservation::STATUS_IN_HOUSE,
            Reservation::STATUS_CANCELLED,
            Reservation::STATUS_NO_SHOW,
        ],
        Reservation::STATUS_IN_HOUSE => [
            Reservation::STATUS_CHECKED_OUT,
        ],
        Reservation::STATUS_CHECKED_OUT => [],
        Reservation::STATUS_CANCELLED => [],
        Reservation::STATUS_NO_SHOW => [],
    ];

    public function __construct(
        private readonly ReservationAvailabilityService $availability,
        private readonly FolioBillingService $billing,
        private readonly RoomStateMachine $roomStateMachine,
        private readonly StayAdjustmentService $stayAdjustmentService,
        private readonly Notifier $notifier,
        private readonly HousekeepingService $housekeepingService,
        private readonly HousekeepingPriorityService $housekeepingPriorityService,
        private readonly VapidEventNotifier $vapidEventNotifier,
    ) {}

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    public function confirm(Reservation $reservation): Reservation
    {
        $this->assertTransition($reservation, Reservation::STATUS_CONFIRMED);
        $this->ensureAvailability($reservation, Reservation::STATUS_CONFIRMED);

        $fromStatus = $reservation->status;
        $updated = $this->applyStatus($reservation, Reservation::STATUS_CONFIRMED);

        activity('reservation')
            ->performedOn($updated)
            ->causedBy(auth()->user())
            ->withProperties([
                'from_status' => $fromStatus,
                'to_status' => $updated->status,
                'reservation_code' => $updated->code,
                'room_id' => $updated->room_id,
                'offer_id' => $updated->offer_id,
                'check_in_date' => $updated->check_in_date,
                'check_out_date' => $updated->check_out_date,
            ])
            ->event('confirmed')
            ->log('confirmed');

        return $updated;
    }

    public function checkIn(
        Reservation $reservation,
        ?Carbon $actualAt = null,
        bool $canOverrideFees = false,
        ?float $earlyOverride = null,
    ): Reservation {
        $this->assertTransition($reservation, Reservation::STATUS_IN_HOUSE);
        $this->ensureAvailability($reservation, Reservation::STATUS_IN_HOUSE);

        if (! $reservation->room_id) {
            throw ValidationException::withMessages([
                'room_id' => 'Une chambre doit être assignée avant le check-in.',
            ]);
        }

        $reservation->loadMissing(['room', 'guest']);

        if (! $reservation->room instanceof Room) {
            throw ValidationException::withMessages([
                'room_id' => 'La chambre sélectionnée est invalide.',
            ]);
        }

        if ($reservation->room->hk_status !== Room::HK_STATUS_INSPECTED) {
            $this->notifier->notify('room.sold_but_dirty', $reservation->hotel_id, [
                'tenant_id' => $reservation->tenant_id,
                'room_id' => $reservation->room_id,
                'room_number' => $reservation->room->number,
                'reservation_id' => $reservation->id,
                'reservation_code' => $reservation->code,
            ], [
                'cta_route' => 'rooms.board',
                'cta_params' => ['date' => now()->toDateString()],
            ]);

            throw ValidationException::withMessages([
                'check_in' => 'La chambre doit être inspectée avant le check-in.',
            ]);
        }

        $actualCheckInAt = $actualAt ?? now();

        $decision = $this->stayAdjustmentService->evaluateEarlyLate($reservation, $actualCheckInAt);

        if ($decision['early_blocked'] && ! $canOverrideFees) {
            throw ValidationException::withMessages([
                'check_in' => $decision['early_reason'] ?? 'Arrivée anticipée non autorisée.',
            ]);
        }

        $this->roomStateMachine->markInUse($reservation->room, $reservation);
        $this->housekeepingService->forceRoomStatus($reservation->room, Room::HK_STATUS_IN_USE, auth()->user());
        $fromStatus = $reservation->status;
        $reservation->actual_check_in_at = $actualCheckInAt;
        $this->billing->ensureMainFolioForReservation($reservation);
        $this->billing->syncStayChargeFromReservation($reservation);

        $updated = $this->applyStatus($reservation, Reservation::STATUS_IN_HOUSE);

        if ($updated->room) {
            $this->housekeepingPriorityService->syncRoomTasks($updated->room, auth()->user());
        }

        $this->stayAdjustmentService->applyFeesToFolio(
            $updated,
            $decision,
            $earlyOverride,
            null,
            $canOverrideFees,
        );

        $this->notifier->notify('reservation.checked_in', $updated->hotel_id, [
            'tenant_id' => $updated->tenant_id,
            'reservation_id' => $updated->id,
            'reservation_code' => $updated->code,
            'room_id' => $updated->room_id,
            'room_number' => $updated->room?->number,
            'guest_name' => $updated->guest?->full_name ?? $updated->guest?->name,
        ], [
            'cta_route' => 'reservations.index',
        ]);

        activity('reservation')
            ->performedOn($updated)
            ->causedBy(auth()->user())
            ->withProperties([
                'from_status' => $fromStatus,
                'to_status' => $updated->status,
                'reservation_code' => $updated->code,
                'room_id' => $updated->room_id,
                'offer_id' => $updated->offer_id,
                'check_in_date' => $updated->check_in_date,
                'check_out_date' => $updated->check_out_date,
                'actual_check_in_at' => $updated->actual_check_in_at,
            ])
            ->event('checked_in')
            ->log('checked_in');

        return $updated;
    }

    public function checkOut(
        Reservation $reservation,
        ?Carbon $actualAt = null,
        bool $canOverrideFees = false,
        ?float $lateOverride = null,
        bool $canOverrideBalance = false,
    ): Reservation {
        $this->assertTransition($reservation, Reservation::STATUS_CHECKED_OUT);

        $reservation->loadMissing(['room', 'guest', 'mainFolio']);

        $mainFolio = $reservation->mainFolio;
        if ($mainFolio && $mainFolio->balance > 0.01 && ! $canOverrideBalance) {
            throw ValidationException::withMessages([
                'check_out' => 'Le folio doit être soldé avant le check-out.',
            ]);
        }

        $actualCheckOutAt = $actualAt ?? now();
        $actualCheckIn = $reservation->actual_check_in_at
            ?? ($reservation->check_in_date ? Carbon::parse($reservation->check_in_date) : $actualCheckOutAt);

        $decision = $this->stayAdjustmentService->evaluateEarlyLate(
            $reservation,
            $actualCheckIn,
            $actualCheckOutAt,
        );

        if ($decision['late_blocked'] && ! $canOverrideFees) {
            throw ValidationException::withMessages([
                'check_out' => $decision['late_reason'] ?? 'Départ tardif non autorisé.',
            ]);
        }

        if ($reservation->room) {
            $this->roomStateMachine->markAvailable($reservation->room);
            $shouldBlockAfterCheckout = (bool) $reservation->room->block_sale_after_checkout;
            $reservation->room->block_sale_after_checkout = false;
            $reservation->room->save();

            $this->housekeepingService->markRoomDirtyAfterCheckout($reservation->room, auth()->user());
            $this->housekeepingService->createTaskAfterCheckout($reservation->room, auth()->user());

            $hasBlockingMaintenance = $reservation->room->maintenanceTickets()
                ->whereIn('status', [
                    MaintenanceTicket::STATUS_OPEN,
                    MaintenanceTicket::STATUS_IN_PROGRESS,
                ])
                ->where('blocks_sale', true)
                ->exists();

            if ($hasBlockingMaintenance || $shouldBlockAfterCheckout) {
                $this->roomStateMachine->markOutOfService($reservation->room);
            }
        }

        $fromStatus = $reservation->status;
        $reservation->actual_check_out_at = $actualCheckOutAt;

        $updated = $this->applyStatus($reservation, Reservation::STATUS_CHECKED_OUT);

        $this->stayAdjustmentService->applyFeesToFolio(
            $updated,
            $decision,
            null,
            $lateOverride,
            $canOverrideFees,
        );

        $remainingBalance = $mainFolio?->balance ?? 0;
        $remainingCurrency = $mainFolio?->currency;

        activity('reservation')
            ->performedOn($updated)
            ->causedBy(auth()->user())
            ->withProperties([
                'from_status' => $fromStatus,
                'to_status' => $updated->status,
                'reservation_code' => $updated->code,
                'room_id' => $updated->room_id,
                'room_number' => $updated->room?->number,
                'offer_id' => $updated->offer_id,
                'check_in_date' => $updated->check_in_date,
                'check_out_date' => $updated->check_out_date,
                'actual_check_out_at' => $updated->actual_check_out_at,
                'remaining_balance' => (float) $remainingBalance,
                'remaining_balance_currency' => $remainingCurrency,
            ])
            ->event('checked_out')
            ->log('checked_out');

        $mainFolio = $updated->mainFolio()->first();
        if ($mainFolio && $mainFolio->balance > 0.01) {
            $this->notifier->notify('folio.balance_remaining_on_checkout', $updated->hotel_id, [
                'tenant_id' => $updated->tenant_id,
                'reservation_id' => $updated->id,
                'reservation_code' => $updated->code,
                'balance' => $mainFolio->balance,
                'currency' => $mainFolio->currency,
            ], [
                'cta_route' => 'reservations.folio.show',
                'cta_params' => ['reservation' => $updated->id],
            ]);
        }

        $this->notifier->notify('reservation.checked_out', $updated->hotel_id, [
            'tenant_id' => $updated->tenant_id,
            'reservation_id' => $updated->id,
            'reservation_code' => $updated->code,
            'room_id' => $updated->room_id,
            'room_number' => $updated->room?->number,
            'guest_name' => $updated->guest?->full_name ?? $updated->guest?->name,
        ], [
            'cta_route' => 'reservations.index',
        ]);

        $this->vapidEventNotifier->notifyOwnersAndManagers(
            eventKey: 'reservation.checked_out',
            tenantId: (string) $updated->tenant_id,
            hotelId: $updated->hotel_id,
            title: 'Check-out effectué',
            body: sprintf(
                '%s est parti (réservation %s, chambre %s).',
                $updated->guest?->full_name ?? $updated->guest?->name ?? 'Client',
                $updated->code ?? '—',
                $updated->room?->number ?? '—',
            ),
            url: route('frontdesk.reservations.details', ['reservation' => $updated->id]),
            tag: 'reservation-checked-out',
        );

        return $updated;
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

        $fromStatus = $reservation->status;
        $updated = $this->applyStatus($reservation, Reservation::STATUS_CANCELLED);

        activity('reservation')
            ->performedOn($updated)
            ->causedBy(auth()->user())
            ->withProperties([
                'from_status' => $fromStatus,
                'to_status' => $updated->status,
                'reservation_code' => $updated->code,
                'room_id' => $updated->room_id,
                'offer_id' => $updated->offer_id,
                'check_in_date' => $updated->check_in_date,
                'check_out_date' => $updated->check_out_date,
            ])
            ->event('cancelled')
            ->log('cancelled');

        return $updated;
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
        $fromStatus = $reservation->status;

        $updated = $this->applyStatus($reservation, Reservation::STATUS_NO_SHOW);

        activity('reservation')
            ->performedOn($updated)
            ->causedBy(auth()->user())
            ->withProperties([
                'from_status' => $fromStatus,
                'to_status' => $updated->status,
                'reservation_code' => $updated->code,
                'room_id' => $updated->room_id,
                'offer_id' => $updated->offer_id,
                'check_in_date' => $updated->check_in_date,
                'check_out_date' => $updated->check_out_date,
            ])
            ->event('no_show')
            ->log('no_show');

        return $updated;
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

    public function getStayAdjustmentService(): StayAdjustmentService
    {
        return $this->stayAdjustmentService;
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
