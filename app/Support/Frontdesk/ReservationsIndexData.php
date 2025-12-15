<?php

declare(strict_types=1);

namespace App\Support\Frontdesk;

use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReservationsIndexData
{
    public static function build(Request $request): array
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $hotelId = $user->active_hotel_id ?? $request->session()->get('active_hotel_id');

        if (! $hotelId) {
            $hotelId = Hotel::query()->where('tenant_id', $tenantId)->value('id');
        }

        $reservations = Reservation::query()
            ->forTenant($tenantId)
            ->when($hotelId, fn ($q) => $q->forHotel($hotelId))
            ->with(['room', 'roomType', 'offer'])
            ->orderBy('check_in_date')
            ->limit(200)
            ->get()
            ->map(function (Reservation $reservation) {
                $start = $reservation->check_in_date ? Carbon::parse($reservation->check_in_date) : null;
                $end = $reservation->check_out_date ? Carbon::parse($reservation->check_out_date) : null;

                if ($end) {
                    $end->addDay();
                }

                // If actual times present, maybe use them? But for calendar "day grid", dates are safer.
                // Let's stick to dates for the calendar view to ensure stability.

                return [
                    'id' => $reservation->id,
                    'title' => $reservation->code,
                    'allDay' => true,
                    'start' => $start?->toDateString(),
                    'end' => $end?->toDateString(),
                    ...self::getStatusColors($reservation->status),
                    'code' => $reservation->code,
                    'status' => $reservation->status,
                    'guest_id' => $reservation->guest_id,
                    'room_type_id' => $reservation->room_type_id,
                    'room_id' => $reservation->room_id,
                    'room_number' => $reservation->room?->number,
                    'room_type_name' => $reservation->roomType?->name,
                    'offer_id' => $reservation->offer_id,
                    'offer_kind' => $reservation->offer?->kind ?? $reservation->offer_kind,
                    'currency' => $reservation->currency,
                    'unit_price' => $reservation->unit_price,
                    'base_amount' => $reservation->base_amount,
                    'tax_amount' => $reservation->tax_amount,
                    'total_amount' => $reservation->total_amount,
                    'adults' => $reservation->adults,
                    'children' => $reservation->children,
                    'notes' => $reservation->notes,
                    'source' => $reservation->source,
                    'expected_arrival_time' => $reservation->expected_arrival_time,
                    'actual_check_in_at' => self::formatDateTimeLocal($reservation->actual_check_in_at),
                    'check_in_date' => self::formatDateTimeLocal($reservation->check_in_date),
                    'check_out_date' => self::formatDateTimeLocal($reservation->check_out_date),
                    'room_hk_status' => $reservation->room?->hk_status,
                ];
            });

        $guests = Guest::query()
            ->forTenant($tenantId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(200)
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Guest $g) => [
                'id' => $g->id,
                'name' => trim($g->first_name.' '.$g->last_name),
            ]);

        $roomTypes = RoomType::query()
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $rooms = Room::query()
            ->where('tenant_id', $tenantId)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->with('roomType')
            ->orderBy('number')
            ->get();

        $offers = Offer::query()
            ->where('tenant_id', $tenantId)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->orderBy('name')
            ->get(['id', 'name', 'kind', 'time_rule', 'time_config']);

        $offerRoomTypePrices = OfferRoomTypePrice::query()
            ->where('tenant_id', $tenantId)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->get(['room_type_id', 'offer_id', 'price', 'currency']);

        return [
            'events' => $reservations,
            'guests' => $guests,
            'roomTypes' => $roomTypes,
            'statusOptions' => Reservation::statusOptions(),
            'rooms' => $rooms->map(fn (Room $room) => [
                'id' => $room->id,
                'number' => $room->number,
                'room_type_id' => $room->room_type_id,
                'room_type_name' => $room->roomType?->name,
                'status' => $room->status,
            ]),
            'offers' => $offers,
            'offerRoomTypePrices' => $offerRoomTypePrices,
            'defaults' => [
                'currency' => 'XAF',
                'hotel_id' => $hotelId,
            ],
            'canManageTimes' => $user->can('reservations.override_datetime'),
        ];
    }

    private static function formatDateTimeLocal(?Carbon $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value->format('Y-m-d\TH:i:s');
    }

    private static function getStatusColors(string $status): array
    {
        return match ($status) {
            Reservation::STATUS_CONFIRMED => [
                'backgroundColor' => '#dbeafe', // blue-100
                'borderColor' => '#3b82f6', // blue-500
                'textColor' => '#1e40af', // blue-800
            ],
            Reservation::STATUS_IN_HOUSE => [
                'backgroundColor' => '#d1fae5', // emerald-100
                'borderColor' => '#10b981', // emerald-500
                'textColor' => '#065f46', // emerald-800
            ],
            Reservation::STATUS_CHECKED_OUT => [
                'backgroundColor' => '#fee2e2', // red-100
                'borderColor' => '#ef4444', // red-500
                'textColor' => '#991b1b', // red-800
            ],
            Reservation::STATUS_CANCELLED => [
                'backgroundColor' => '#f3f4f6', // gray-100
                'borderColor' => '#9ca3af', // gray-400
                'textColor' => '#374151', // gray-700
                'textDecoration' => 'line-through', // Optional: keep strikethrough if supported or just purely color
            ],
            Reservation::STATUS_NO_SHOW => [
                'backgroundColor' => '#18181b', // zinc-950 (Black-ish)
                'borderColor' => '#000000', // black
                'textColor' => '#ffffff', // white
            ],
            default => [ // Pending and others
                'backgroundColor' => '#ffedd5', // orange-100
                'borderColor' => '#f59e0b', // amber-500 (less reddish/orange than previous)
                'textColor' => '#9a3412', // orange-800
            ],
        };
    }
}
