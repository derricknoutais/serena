<?php

namespace App\Services;

use App\Models\MaintenanceTicket;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Validation\ValidationException;

class ReservationAvailabilityService
{
    public function ensureAvailable(array $data, ?int $ignoreReservationId = null): void
    {
        if (! $this->isActiveStatus($data['status'] ?? null)) {
            return;
        }

        if ($this->hasRoomConflict($data, $ignoreReservationId)) {
            throw ValidationException::withMessages([
                'room_id' => ['La chambre sélectionnée n’est pas disponible sur cette période.'],
            ]);
        }

        if ($this->roomIsBlockedByMaintenance($data)) {
            throw ValidationException::withMessages([
                'room_id' => ['La chambre sélectionnée est bloquée par la maintenance.'],
            ]);
        }

        if ($this->exceedsRoomTypeCapacity($data, $ignoreReservationId)) {
            throw ValidationException::withMessages([
                'room_type_id' => ['Aucune chambre disponible pour ce type sur cette période.'],
            ]);
        }
    }

    protected function hasRoomConflict(array $data, ?int $ignoreReservationId = null): bool
    {
        $roomId = $data['room_id'] ?? null;
        $tenantId = $data['tenant_id'] ?? null;
        $hotelId = $data['hotel_id'] ?? null;

        if (! $roomId || ! $tenantId || ! $hotelId) {
            return false;
        }

        return Reservation::query()
            ->where('room_id', $roomId)
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereIn('status', Reservation::activeStatusForAvailability())
            ->when($ignoreReservationId, fn ($query) => $query->where('id', '!=', $ignoreReservationId))
            ->where(function ($query) use ($data) {
                $query->where('check_in_date', '<', $data['check_out_date'])
                    ->where('check_out_date', '>', $data['check_in_date']);
            })
            ->exists();
    }

    protected function exceedsRoomTypeCapacity(array $data, ?int $ignoreReservationId = null): bool
    {
        $roomTypeId = $data['room_type_id'] ?? null;
        $roomId = $data['room_id'] ?? null;
        $tenantId = $data['tenant_id'] ?? null;
        $hotelId = $data['hotel_id'] ?? null;

        if ($roomId) {
            return false;
        }

        if (! $roomTypeId || ! $tenantId || ! $hotelId) {
            return false;
        }

        $activeRooms = Room::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->sellable()
            ->count();

        if ($activeRooms <= 0) {
            return true;
        }

        $overlappingReservations = Reservation::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->whereIn('status', Reservation::activeStatusForAvailability())
            ->when($ignoreReservationId, fn ($query) => $query->where('id', '!=', $ignoreReservationId))
            ->where(function ($query) use ($data) {
                $query->where('check_in_date', '<', $data['check_out_date'])
                    ->where('check_out_date', '>', $data['check_in_date']);
            })
            ->count();

        return $overlappingReservations >= $activeRooms;
    }

    protected function roomIsBlockedByMaintenance(array $data): bool
    {
        $roomId = $data['room_id'] ?? null;
        $tenantId = $data['tenant_id'] ?? null;
        $hotelId = $data['hotel_id'] ?? null;

        if (! $roomId || ! $tenantId || ! $hotelId) {
            return false;
        }

        return Room::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('id', $roomId)
            ->whereHas('maintenanceTickets', function ($ticketQuery): void {
                $ticketQuery
                    ->whereIn('status', [
                        MaintenanceTicket::STATUS_OPEN,
                        MaintenanceTicket::STATUS_IN_PROGRESS,
                    ])
                    ->where('blocks_sale', true);
            })
            ->exists();
    }

    private function isActiveStatus(?string $status): bool
    {
        if ($status === null) {
            return true;
        }

        return in_array($status, Reservation::activeStatusForAvailability(), true);
    }
}
