<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\FolioItem;
use App\Models\Hotel;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NightAuditService
{
    public function __construct(private readonly BusinessDayService $businessDayService) {}

    /**
     * @return array<string, mixed>
     */
    public function cachedGenerate(int $tenantId, int $hotelId, Carbon $businessDate, bool $refresh = false): array
    {
        $key = sprintf('night_audit:%d:%d:%s', $tenantId, $hotelId, $businessDate->toDateString());

        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes(30), function () use ($tenantId, $hotelId, $businessDate) {
            return $this->generate($tenantId, $hotelId, $businessDate);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(int $tenantId, int $hotelId, Carbon $businessDate): array
    {
        $hotel = Hotel::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($hotelId);

        [$windowStart, $windowEnd] = $this->businessWindow($hotel, $businessDate);

        $totalRooms = Room::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->count();

        $occupiedRooms = Reservation::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('status', Reservation::STATUS_IN_HOUSE)
            ->whereDate('check_in_date', '<=', $windowStart)
            ->whereDate('check_out_date', '>=', $windowStart)
            ->count();

        $availableRooms = max($totalRooms - $occupiedRooms, 0);
        $occupancyRate = $totalRooms > 0
            ? round(($occupiedRooms / $totalRooms) * 100, 1)
            : 0.0;

        $arrivals = Reservation::query()
            ->select('id', 'code', 'room_id', 'guest_id', 'actual_check_in_at', 'check_in_date', 'check_out_date')
            ->with(['guest:id,first_name,last_name', 'room:id,number'])
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereBetween('actual_check_in_at', [$windowStart, $windowEnd])
            ->orderBy('actual_check_in_at')
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'code' => $reservation->code,
                'room' => $reservation->room?->number,
                'guest' => $reservation->guest?->full_name ?? $reservation->guest?->name,
                'check_in_at' => optional($reservation->actual_check_in_at)->toDateTimeString(),
            ]);

        $departures = Reservation::query()
            ->select('id', 'code', 'room_id', 'guest_id', 'actual_check_out_at', 'check_in_date', 'check_out_date')
            ->with(['guest:id,first_name,last_name', 'room:id,number'])
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereBetween('actual_check_out_at', [$windowStart, $windowEnd])
            ->orderBy('actual_check_out_at')
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'code' => $reservation->code,
                'room' => $reservation->room?->number,
                'guest' => $reservation->guest?->full_name ?? $reservation->guest?->name,
                'check_out_at' => optional($reservation->actual_check_out_at)->toDateTimeString(),
            ]);

        $inHouseList = Reservation::query()
            ->select('id', 'code', 'room_id', 'guest_id', 'check_in_date', 'check_out_date', 'status')
            ->with(['guest:id,first_name,last_name', 'room:id,number'])
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('status', Reservation::STATUS_IN_HOUSE)
            ->whereDate('check_in_date', '<=', $windowStart)
            ->whereDate('check_out_date', '>=', $windowStart)
            ->limit(20)
            ->orderBy('room_id')
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'code' => $reservation->code,
                'room' => $reservation->room?->number,
                'guest' => $reservation->guest?->full_name ?? $reservation->guest?->name,
                'check_in_date' => optional($reservation->check_in_date)?->toDateString(),
                'check_out_date' => optional($reservation->check_out_date)?->toDateString(),
            ]);

        $revenueBreakdown = $this->computeRevenue($tenantId, $hotelId, $businessDate);
        $roomRevenue = $revenueBreakdown['room_revenue'];
        $posRevenue = $revenueBreakdown['pos_revenue'];
        $taxTotal = $revenueBreakdown['tax_total'];
        $totalRevenue = $revenueBreakdown['total_revenue'];

        $payments = Payment::query()
            ->select('payment_method_id', 'amount')
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('business_date', $businessDate->toDateString())
            ->with('paymentMethod:id,name')
            ->get();

        $paymentsByMethod = $payments
            ->groupBy(fn (Payment $payment) => $payment->paymentMethod?->name ?? 'Inconnu')
            ->map(fn (Collection $group) => $group->sum('amount'))
            ->toArray();

        $totalPayments = array_sum($paymentsByMethod);

        $cashSessions = CashSession::query()
            ->with(['openedBy:id,name', 'closedBy:id,name'])
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereIn('status', ['closed', 'closed_pending_validation', 'open'])
            ->where('business_date', $businessDate->toDateString())
            ->orderBy('ended_at')
            ->get()
            ->map(fn (CashSession $session): array => [
                'type' => $session->type,
                'opened_at' => optional($session->started_at)->toDateTimeString(),
                'closed_at' => optional($session->ended_at)->toDateTimeString(),
                'opened_by' => $session->openedBy?->name,
                'closed_by' => $session->closedBy?->name,
                'opening_amount' => (float) $session->starting_amount,
                'cash_in' => (float) Payment::query()
                    ->where('cash_session_id', $session->id)
                    ->sum('amount'),
                'expected_close' => (float) ($session->expected_closing_amount ?? 0),
                'actual_close' => (float) ($session->closing_amount ?? 0),
                'difference' => (float) ($session->difference_amount ?? 0),
            ])
            ->values();

        $cashTotals = $cashSessions
            ->groupBy('type')
            ->map(fn (Collection $group) => [
                'opening_amount' => (float) $group->sum('opening_amount'),
                'cash_in' => (float) $group->sum('cash_in'),
                'expected_close' => (float) $group->sum('expected_close'),
                'actual_close' => (float) $group->sum('actual_close'),
                'difference' => (float) $group->sum('difference'),
            ])
            ->toArray();

        $cashTotals['total'] = [
            'opening_amount' => (float) $cashSessions->sum('opening_amount'),
            'cash_in' => (float) $cashSessions->sum('cash_in'),
            'expected_close' => (float) $cashSessions->sum('expected_close'),
            'actual_close' => (float) $cashSessions->sum('actual_close'),
            'difference' => (float) $cashSessions->sum('difference'),
        ];

        return [
            'hotel' => [
                'id' => $hotel->id,
                'name' => $hotel->name,
                'currency' => $hotel->currency,
            ],
            'business_date' => $businessDate->toDateString(),
            'occupancy' => [
                'total_rooms' => $totalRooms,
                'occupied_rooms' => $occupiedRooms,
                'available_rooms' => $availableRooms,
                'occupancy_rate' => $occupancyRate,
            ],
            'movements' => [
                'arrivals' => $arrivals,
                'departures' => $departures,
                'in_house' => $inHouseList,
            ],
            'revenue' => [
                'room_revenue' => $roomRevenue,
                'pos_revenue' => $posRevenue,
                'tax_total' => $taxTotal,
                'total_revenue' => $totalRevenue,
            ],
            'payments_by_method' => $paymentsByMethod,
            'total_payments' => $totalPayments,
            'cash_reconciliation' => [
                'sessions' => $cashSessions,
                'totals' => $cashTotals,
            ],
            'window' => [
                'start' => $windowStart->toDateTimeString(),
                'end' => $windowEnd->toDateTimeString(),
            ],
        ];
    }

    /**
     * @return array{room_revenue: float, pos_revenue: float, tax_total: float, total_revenue: float}
     */
    private function computeRevenue(int $tenantId, int $hotelId, Carbon $businessDate): array
    {
        $date = $businessDate->toDateString();

        $invoiceItems = InvoiceItem::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('invoice', function ($query) use ($hotelId, $date) {
                $query->where('hotel_id', $hotelId)
                    ->where('business_date', $date);
            })
            ->with('folioItem')
            ->get();

        if ($invoiceItems->isNotEmpty()) {
            $roomRevenue = (float) $invoiceItems
                ->filter(fn (InvoiceItem $item) => $item->folioItem?->is_stay_item === true)
                ->sum('total_amount');

            $posRevenue = (float) $invoiceItems
                ->filter(fn (InvoiceItem $item) => $item->folioItem?->is_stay_item === false)
                ->sum('total_amount');

            $taxTotal = (float) $invoiceItems->sum('tax_amount');

            return [
                'room_revenue' => $roomRevenue,
                'pos_revenue' => $posRevenue,
                'tax_total' => $taxTotal,
                'total_revenue' => $roomRevenue + $posRevenue,
            ];
        }

        $folioItems = FolioItem::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('business_date', $date)
            ->get(['is_stay_item', 'total_amount', 'tax_amount']);

        $roomRevenue = (float) $folioItems->where('is_stay_item', true)->sum('total_amount');
        $posRevenue = (float) $folioItems->where('is_stay_item', false)->sum('total_amount');
        $taxTotal = (float) $folioItems->sum('tax_amount');

        return [
            'room_revenue' => $roomRevenue,
            'pos_revenue' => $posRevenue,
            'tax_total' => $taxTotal,
            'total_revenue' => $roomRevenue + $posRevenue,
        ];
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    private function businessWindow(Hotel $hotel, Carbon $businessDate): array
    {
        return $this->businessDayService->businessWindow($hotel, $businessDate);
    }
}
