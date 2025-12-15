<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function summary(string $tenantId, int $hotelId, Carbon $from, Carbon $to): array
    {
        $totalRooms = Room::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('status', '!=', 'out_of_order')
            ->count();

        $activeStatuses = [
            Reservation::STATUS_PENDING,
            Reservation::STATUS_CONFIRMED,
            Reservation::STATUS_IN_HOUSE,
            Reservation::STATUS_CHECKED_OUT,
        ];

        $reservations = Reservation::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_NO_SHOW])
            ->where('check_in_date', '<', $to)
            ->where('check_out_date', '>', $from)
            ->get();

        $roomsSold = $reservations->count();
        $days = max(1, $from->diffInDays($to) ?: 1);
        $occupancyRate = $totalRooms > 0 ? round(($roomsSold / ($totalRooms * $days)) * 100, 1) : 0.0;

        $arrivals = $reservations->whereBetween('check_in_date', [$from, $to])->count();
        $departures = $reservations->whereBetween('check_out_date', [$from, $to])->count();

        $folioQuery = FolioItem::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereBetween(DB::raw('DATE(date)'), [$from->toDateString(), $to->toDateString()]);

        $revenueRooms = (clone $folioQuery)->where(function ($q): void {
            $q->where('is_stay_item', true)->orWhere('type', 'stay');
        })->sum('total_amount');

        $revenuePos = (clone $folioQuery)->where(function ($q): void {
            $q->whereNull('is_stay_item')->orWhere('is_stay_item', false);
        })->sum('total_amount');

        $revenueTotal = $revenueRooms + $revenuePos;

        $paymentsTotal = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($hotelId): void {
                $q->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
            })
            ->whereBetween(DB::raw('DATE(paid_at)'), [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $cashDiff = CashSession::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereBetween(DB::raw('DATE(ended_at)'), [$from->toDateString(), $to->toDateString()])
            ->sum('difference_amount');

        return [
            'occupancy_rate' => $occupancyRate,
            'rooms_sold' => $roomsSold,
            'arrivals' => $arrivals,
            'departures' => $departures,
            'revenue_total' => (float) $revenueTotal,
            'revenue_rooms' => (float) $revenueRooms,
            'revenue_pos' => (float) $revenuePos,
            'payments_total' => (float) $paymentsTotal,
            'cash_difference' => (float) $cashDiff,
        ];
    }

    public function trends(string $tenantId, int $hotelId, Carbon $from, Carbon $to): array
    {
        $dates = $this->dateRange($from, $to);
        $activeStatuses = [
            Reservation::STATUS_PENDING,
            Reservation::STATUS_CONFIRMED,
            Reservation::STATUS_IN_HOUSE,
            Reservation::STATUS_CHECKED_OUT,
        ];

        $reservations = Reservation::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereIn('status', $activeStatuses)
            ->where('check_in_date', '<', $to)
            ->where('check_out_date', '>', $from)
            ->get(['check_in_date', 'check_out_date', 'status']);

        $folioItems = FolioItem::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereBetween(DB::raw('DATE(date)'), [$from->toDateString(), $to->toDateString()])
            ->get(['date', 'is_stay_item', 'type', 'total_amount']);

        $occupancySeries = [];
        $revenueRoomSeries = [];
        $revenuePosSeries = [];

        foreach ($dates as $date) {
            $occupancySeries[] = [
                'date' => $date->toDateString(),
                'value' => $reservations->filter(function ($res) use ($date): bool {
                    return $res->check_in_date <= $date && $res->check_out_date > $date;
                })->count(),
            ];

            $revenueRoomSeries[] = [
                'date' => $date->toDateString(),
                'value' => (float) $folioItems
                    ->filter(fn ($item) => $item->date?->toDateString() === $date->toDateString() && ($item->is_stay_item || $item->type === 'stay'))
                    ->sum('total_amount'),
            ];

            $revenuePosSeries[] = [
                'date' => $date->toDateString(),
                'value' => (float) $folioItems
                    ->filter(fn ($item) => $item->date?->toDateString() === $date->toDateString() && (! $item->is_stay_item && $item->type !== 'stay'))
                    ->sum('total_amount'),
            ];
        }

        return [
            'occupancy' => $occupancySeries,
            'revenue_rooms' => $revenueRoomSeries,
            'revenue_pos' => $revenuePosSeries,
        ];
    }

    public function paymentsByMethod(string $tenantId, int $hotelId, Carbon $from, Carbon $to): array
    {
        return Payment::query()
            ->select(['payment_method_id', DB::raw('SUM(amount) as total')])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($hotelId): void {
                $q->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
            })
            ->whereBetween(DB::raw('DATE(paid_at)'), [$from->toDateString(), $to->toDateString()])
            ->groupBy('payment_method_id')
            ->with('paymentMethod:id,name,type')
            ->get()
            ->map(fn ($row) => [
                'payment_method_id' => $row->payment_method_id,
                'payment_method_name' => $row->paymentMethod?->name ?? $row->paymentMethod?->type ?? 'Inconnu',
                'total' => (float) $row->total,
            ])
            ->values()
            ->all();
    }

    public function cashDifferences(string $tenantId, int $hotelId, Carbon $from, Carbon $to): array
    {
        return CashSession::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereNotNull('ended_at')
            ->whereBetween(DB::raw('DATE(ended_at)'), [$from->toDateString(), $to->toDateString()])
            ->get(['id', 'type', 'difference_amount', 'expected_closing_amount', 'closing_amount'])
            ->map(fn ($session) => [
                'id' => $session->id,
                'type' => $session->type,
                'difference' => (float) ($session->difference_amount ?? 0),
                'expected' => (float) ($session->expected_closing_amount ?? 0),
                'actual' => (float) ($session->closing_amount ?? 0),
            ])
            ->values()
            ->all();
    }

    public function topProducts(string $tenantId, int $hotelId, Carbon $from, Carbon $to, int $limit = 5): array
    {
        return FolioItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(total_amount) as revenue'))
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereNotNull('product_id')
            ->whereBetween(DB::raw('DATE(date)'), [$from->toDateString(), $to->toDateString()])
            ->groupBy('product_id')
            ->orderByDesc(DB::raw('SUM(total_amount)'))
            ->limit($limit)
            ->with('product:id,name')
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->product?->name ?? 'Produit',
                'qty' => (float) $row->qty,
                'revenue' => (float) $row->revenue,
            ])
            ->values()
            ->all();
    }

    // Payments are not linked to guests in the schema, so skip top guests for now.
    public function topGuests(string $tenantId, int $hotelId, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return [];
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function dateRange(Carbon $from, Carbon $to): Collection
    {
        $dates = collect();
        $cursor = $from->copy();

        while ($cursor->lessThanOrEqualTo($to)) {
            $dates->push($cursor->copy());
            $cursor->addDay();
        }

        return $dates;
    }
}
