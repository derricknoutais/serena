<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Validation\ValidationException;

class RoomStateMachine
{
    public function canTransition(string $from, string $to): bool
    {
        $map = [
            Room::STATUS_AVAILABLE => [
                Room::STATUS_OCCUPIED,
                Room::STATUS_OUT_OF_ORDER,
            ],
            Room::STATUS_OCCUPIED => [
                Room::STATUS_AVAILABLE,
            ],
            Room::STATUS_OUT_OF_ORDER => [
                Room::STATUS_AVAILABLE,
            ],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }

    public function markOccupied(Room $room, Reservation $reservation): Room
    {
        $this->assertTransition($room, Room::STATUS_OCCUPIED);

        if ((string) $reservation->room_id !== (string) $room->id) {
            throw ValidationException::withMessages([
                'room_id' => 'La chambre sélectionnée ne correspond pas à la réservation.',
            ]);
        }

        $room->status = Room::STATUS_OCCUPIED;
        $room->save();

        return $room;
    }

    public function markAvailable(Room $room): Room
    {
        if (! in_array($room->status, [Room::STATUS_OCCUPIED, Room::STATUS_OUT_OF_ORDER], true)) {
            return $room;
        }

        $room->status = Room::STATUS_AVAILABLE;
        $room->save();

        return $room;
    }

    public function markOutOfOrder(Room $room): Room
    {
        if ($room->status === Room::STATUS_OCCUPIED) {
            throw ValidationException::withMessages([
                'room_status' => 'Impossible de mettre la chambre hors service pendant un séjour.',
            ]);
        }

        if ($room->status === Room::STATUS_OUT_OF_ORDER) {
            return $room;
        }

        $room->status = Room::STATUS_OUT_OF_ORDER;
        $room->save();

        return $room;
    }

    private function assertTransition(Room $room, string $targetStatus): void
    {
        if (! $this->canTransition($room->status, $targetStatus)) {
            throw ValidationException::withMessages([
                'room_status' => 'Transition de statut de chambre non autorisée.',
            ]);
        }
    }
}
