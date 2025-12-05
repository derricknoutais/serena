<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Room;
use App\Services\RoomStateMachine;
use Illuminate\Validation\ValidationException;

it('marks a room as occupied and available again', function (): void {
    [
        'reservation' => $reservation,
        'room' => $room,
    ] = setupReservationEnvironment('room-sm');

    $roomStateMachine = app(RoomStateMachine::class);

    $roomStateMachine->markOccupied($room->fresh(), $reservation->fresh());

    expect($room->fresh()->status)->toBe(Room::STATUS_OCCUPIED);

    $roomStateMachine->markAvailable($room->fresh());

    expect($room->fresh()->status)->toBe(Room::STATUS_AVAILABLE);
});

it('prevents marking out of order while occupied', function (): void {
    [
        'reservation' => $reservation,
        'room' => $room,
    ] = setupReservationEnvironment('room-sm-invalid');

    $room->forceFill(['status' => Room::STATUS_OCCUPIED])->save();

    $roomStateMachine = app(RoomStateMachine::class);

    expect(fn () => $roomStateMachine->markOutOfOrder($room->fresh()))
        ->toThrow(ValidationException::class);
});
