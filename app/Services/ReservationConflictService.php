<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReservationConflictService
{
    public function __construct(
        private readonly ?Notifier $notifier = null,
    ) {}

    /**
     * @return array{id:int, code:string|null, guest:string|null, room:string|null, overlap:string}|null
     */
    public function detectRoomConflict(
        int $hotelId,
        string $roomId,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $excludeReservationId = null,
        ?string $tenantId = null,
    ): ?array {
        $conflict = $this->getBlockingReservationsQuery(
            $hotelId,
            $roomId,
            $checkIn,
            $checkOut,
            $tenantId,
            $excludeReservationId,
        )
            ->select('id', 'code', 'guest_id', 'room_id', 'check_in_date', 'check_out_date')
            ->with(['guest:id,first_name,last_name', 'room:id,number'])
            ->first();

        if (! $conflict) {
            return null;
        }

        return [
            'id' => $conflict->id,
            'code' => $conflict->code,
            'guest' => $conflict->guest?->full_name ?? $conflict->guest?->name,
            'room' => $conflict->room?->number,
            'overlap' => sprintf(
                '%s au %s',
                optional($conflict->check_in_date)?->format('d/m/Y'),
                optional($conflict->check_out_date)?->format('d/m/Y'),
            ),
        ];
    }

    /**
     * @return array{date:string, demand:int, supply:int}|null
     */
    public function detectRoomTypeOverbooking(
        int $hotelId,
        int $roomTypeId,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $excludeReservationId = null,
    ): ?array {
        $supply = Room::query()
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->sellable()
            ->count();

        if ($supply <= 0) {
            return [
                'date' => $checkIn->toDateString(),
                'demand' => 0,
                'supply' => 0,
            ];
        }

        $cursor = $checkIn->copy();
        $end = $checkOut->copy();

        while ($cursor->lessThan($end)) {
            $dateString = $cursor->toDateString();

            $demand = Reservation::query()
                ->where('hotel_id', $hotelId)
                ->where('room_type_id', $roomTypeId)
                ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_NO_SHOW])
                ->whereDate('check_in_date', '<=', $dateString)
                ->whereDate('check_out_date', '>', $dateString)
                ->when($excludeReservationId, fn ($q) => $q->where('id', '!=', $excludeReservationId))
                ->count();

            if ($demand >= $supply) {
                return [
                    'date' => $dateString,
                    'demand' => $demand,
                    'supply' => $supply,
                ];
            }

            $cursor->addDay();
        }

        return null;
    }

    public function validateOrThrowRoomConflict(
        int $hotelId,
        string $roomId,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $excludeReservationId = null,
        ?string $tenantId = null,
    ): void {
        $conflict = $this->detectRoomConflict(
            $hotelId,
            $roomId,
            $checkIn,
            $checkOut,
            $excludeReservationId,
            $tenantId,
        );

        if ($conflict) {
            $message = sprintf(
                'Conflit: la chambre %s est déjà réservée (%s) du %s.',
                $conflict['room'] ?? 'N/A',
                $conflict['code'] ?? ('#'.$conflict['id']),
                $conflict['overlap'],
            );

            $this->notifyConflict($hotelId, $tenantId, [
                'room_id' => $roomId,
                'room_number' => $conflict['room'] ?? null,
                'existing_reservation_id' => $conflict['id'],
                'existing_code' => $conflict['code'],
                'reservation_code' => $conflict['code'],
            ]);

            throw ValidationException::withMessages([
                'room_id' => $message,
            ]);
        }
    }

    public function getBlockingReservationsQuery(
        int $hotelId,
        string $roomId,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?string $tenantId = null,
        ?int $excludeReservationId = null,
    ): Builder {
        return Reservation::query()
            ->where('hotel_id', $hotelId)
            ->when($tenantId, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->where('room_id', $roomId)
            ->whereIn('status', Reservation::activeStatusForAvailability())
            ->whereNull('actual_check_out_at')
            ->where('check_in_date', '<', $rangeEnd)
            ->where('check_out_date', '>', $rangeStart)
            ->when($excludeReservationId, fn (Builder $query) => $query->where('id', '!=', $excludeReservationId));
    }

    public function validateOrThrowOverbooking(
        int $hotelId,
        int $roomTypeId,
        Carbon $checkIn,
        Carbon $checkOut,
        ?int $excludeReservationId = null,
        ?string $tenantId = null,
    ): void {
        $over = $this->detectRoomTypeOverbooking($hotelId, $roomTypeId, $checkIn, $checkOut, $excludeReservationId);

        if ($over) {
            $this->notifyConflict($hotelId, $tenantId, [
                'room_type_id' => $roomTypeId,
                'date' => $over['date'],
                'demand' => $over['demand'],
                'supply' => $over['supply'],
            ]);

            throw ValidationException::withMessages([
                'room_type_id' => sprintf(
                    'Surbooking: plus de disponibilité pour ce type le %s (demandées: %d, disponibles: %d).',
                    Carbon::parse($over['date'])->format('d/m/Y'),
                    $over['demand'],
                    $over['supply'],
                ),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyConflict(int $hotelId, ?string $tenantId, array $payload): void
    {
        if (! $this->notifier) {
            return;
        }

        $this->notifier->notify('reservation.conflict_detected', $hotelId, [
            ...$payload,
            'tenant_id' => $tenantId ?? auth()->user()?->tenant_id,
            'hotel_id' => $hotelId,
        ], [
            'cta_route' => 'reservations.index',
        ]);
    }
}
