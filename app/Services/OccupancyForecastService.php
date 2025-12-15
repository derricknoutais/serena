<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OccupancyForecastService
{
    /**
     * @return array<string, mixed>
     */
    public function generate(int $tenantId, int $hotelId, int $days = 7): array
    {
        $days = in_array($days, [7, 14], true) ? $days : 7;

        $startDate = Carbon::today();
        $endDate = $startDate->copy()->addDays($days - 1);

        $totalRooms = Room::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('status', '!=', 'out_of_order')
            ->count();

        $statusFilter = [
            Reservation::STATUS_PENDING,
            Reservation::STATUS_CONFIRMED,
            Reservation::STATUS_IN_HOUSE,
            Reservation::STATUS_CHECKED_OUT,
        ];

        $arrivals = Reservation::query()
            ->selectRaw('DATE(check_in_date) as d, COUNT(*) as count')
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereBetween('check_in_date', [$startDate, $endDate])
            ->whereIn('status', $statusFilter)
            ->groupBy('d')
            ->pluck('count', 'd');

        $departures = Reservation::query()
            ->selectRaw('DATE(check_out_date) as d, COUNT(*) as count')
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereBetween('check_out_date', [$startDate, $endDate])
            ->whereIn('status', $statusFilter)
            ->groupBy('d')
            ->pluck('count', 'd');

        $soldReservations = Reservation::query()
            ->select('id', 'check_in_date', 'check_out_date')
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('check_in_date', '<=', $endDate)
            ->where('check_out_date', '>', $startDate)
            ->whereIn('status', $statusFilter)
            ->get();

        $rows = [];
        $cursor = $startDate->copy();

        while ($cursor->lessThanOrEqualTo($endDate)) {
            $dateString = $cursor->toDateString();
            $soldRooms = $this->soldRoomsForDate($soldReservations, $cursor);
            $occupancyRate = $totalRooms > 0 ? round(($soldRooms / $totalRooms) * 100, 1) : 0.0;

            $rows[] = [
                'date' => $dateString,
                'sold_rooms' => $soldRooms,
                'arrivals' => (int) ($arrivals[$dateString] ?? 0),
                'departures' => (int) ($departures[$dateString] ?? 0),
                'occupancy_rate' => $occupancyRate,
            ];

            $cursor->addDay();
        }

        return [
            'total_rooms' => $totalRooms,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     */
    private function soldRoomsForDate(Collection $reservations, Carbon $date): int
    {
        return $reservations
            ->filter(function (Reservation $reservation) use ($date) {
                return $reservation->check_in_date?->toDateString() <= $date->toDateString()
                    && $reservation->check_out_date?->toDateString() > $date->toDateString();
            })
            ->count();
    }
}
