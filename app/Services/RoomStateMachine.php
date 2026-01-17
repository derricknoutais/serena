<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Validation\ValidationException;

class RoomStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        Room::STATUS_AVAILABLE => [
            Room::STATUS_OCCUPIED,
            Room::STATUS_IN_USE,
            Room::STATUS_OUT_OF_ORDER,
        ],
        Room::STATUS_OCCUPIED => [
            Room::STATUS_AVAILABLE,
        ],
        Room::STATUS_IN_USE => [
            Room::STATUS_AVAILABLE,
            Room::STATUS_OUT_OF_ORDER,
        ],
        Room::STATUS_OUT_OF_ORDER => [
            Room::STATUS_AVAILABLE,
        ],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
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

    public function markInUse(Room $room, Reservation $reservation): Room
    {
        $this->assertTransition($room, Room::STATUS_IN_USE);

        if ((string) $reservation->room_id !== (string) $room->id) {
            throw ValidationException::withMessages([
                'room_id' => 'La chambre sélectionnée ne correspond pas à la réservation.',
            ]);
        }

        $room->status = Room::STATUS_IN_USE;
        $room->save();

        return $room;
    }

    public function markAvailable(Room $room): Room
    {
        if (! in_array($room->status, [Room::STATUS_OCCUPIED, Room::STATUS_IN_USE, Room::STATUS_OUT_OF_ORDER], true)) {
            return $room;
        }

        $room->status = Room::STATUS_AVAILABLE;
        $room->save();

        return $room;
    }

    public function markOutOfService(Room $room): Room
    {
        if (in_array($room->status, [Room::STATUS_OCCUPIED, Room::STATUS_IN_USE], true)) {
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
