<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\MaintenanceTicket;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationStateMachine;
use Illuminate\Validation\ValidationException;

it('confirms a pending reservation', function (): void {
    [
        'reservation' => $reservation,
    ] = setupReservationEnvironment('sm-confirm');

    $reservation->forceFill(['status' => Reservation::STATUS_PENDING])->save();

    $stateMachine = app(ReservationStateMachine::class);
    $stateMachine->confirm($reservation->fresh());

    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_CONFIRMED);
});

it('marks a room occupied on check in', function (): void {
    [
        'reservation' => $reservation,
    ] = setupReservationEnvironment('sm-checkin');

    $reservation->forceFill(['status' => Reservation::STATUS_CONFIRMED])->save();

    $stateMachine = app(ReservationStateMachine::class);
    $stateMachine->checkIn($reservation->fresh());

    $fresh = $reservation->fresh(['room']);

    expect($fresh->status)->toBe(Reservation::STATUS_IN_HOUSE)
        ->and($fresh->actual_check_in_at)->not->toBeNull()
        ->and($fresh->room?->status)->toBe(Room::STATUS_OCCUPIED);
});

it('releases the room on check out', function (): void {
    [
        'reservation' => $reservation,
    ] = setupReservationEnvironment('sm-checkout');

    $reservation->forceFill([
        'status' => Reservation::STATUS_IN_HOUSE,
        'actual_check_in_at' => now()->subDay(),
    ])->save();

    $stateMachine = app(ReservationStateMachine::class);
    $stateMachine->checkOut($reservation->fresh());

    $fresh = $reservation->fresh(['room']);

    expect($fresh->status)->toBe(Reservation::STATUS_CHECKED_OUT)
        ->and($fresh->actual_check_out_at)->not->toBeNull()
        ->and($fresh->room?->status)->toBe(Room::STATUS_AVAILABLE)
        ->and($fresh->room?->hk_status)->toBe('dirty');
});

it('marks rooms out of order after checkout when blocking maintenance is open', function (): void {
    [
        'reservation' => $reservation,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('sm-checkout-blocked');

    $reservation->forceFill([
        'status' => Reservation::STATUS_IN_HOUSE,
        'actual_check_in_at' => now()->subDay(),
    ])->save();

    MaintenanceTicket::query()->create([
        'tenant_id' => $reservation->tenant_id,
        'hotel_id' => $reservation->hotel_id,
        'room_id' => $room->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_OPEN,
        'severity' => MaintenanceTicket::SEVERITY_HIGH,
        'blocks_sale' => true,
        'title' => 'Climatisation',
        'opened_at' => now(),
    ]);

    $stateMachine = app(ReservationStateMachine::class);
    $stateMachine->checkOut($reservation->fresh());

    $fresh = $reservation->fresh(['room']);

    expect($fresh->status)->toBe(Reservation::STATUS_CHECKED_OUT)
        ->and($fresh->room?->status)->toBe(Room::STATUS_OUT_OF_ORDER)
        ->and($fresh->room?->hk_status)->toBe('dirty');
});

it('rejects invalid transitions', function (): void {
    [
        'reservation' => $reservation,
    ] = setupReservationEnvironment('sm-invalid');

    $reservation->forceFill(['status' => Reservation::STATUS_PENDING])->save();

    $stateMachine = app(ReservationStateMachine::class);

    expect(fn () => $stateMachine->checkOut($reservation->fresh()))
        ->toThrow(ValidationException::class);
});
