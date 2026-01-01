<?php

declare(strict_types=1);

namespace App\Support\Frontdesk;

use App\Models\Guest;
use App\Models\MaintenanceTicket;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RoomBoardData
{
    public static function build(Request $request): array
    {
        $user = $request->user();

        $tenantId = (string) $user->tenant_id;
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);
        $canManageHousekeeping = $user->hasRole(['owner', 'manager', 'housekeeping', 'superadmin']);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        $dateParam = $request->query('date');
        $date = $dateParam ? Carbon::parse((string) $dateParam)->startOfDay() : now()->startOfDay();
        $dateString = $date->toDateString();

        $rooms = Room::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->with('roomType')
            ->orderBy('floor')
            ->orderBy('number')
            ->get();

        $activeTickets = MaintenanceTicket::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereIn('status', [
                MaintenanceTicket::STATUS_OPEN,
                MaintenanceTicket::STATUS_IN_PROGRESS,
            ])
            ->with(['assignedTo:id,name', 'reportedBy:id,name'])
            ->get()
            ->groupBy('room_id');

        $reservations = Reservation::query()
            ->forTenant($tenantId)
            ->forHotel($hotelId)
            ->where(function ($query) use ($dateString): void {
                $query->whereDate('check_in_date', '<=', $dateString)
                    ->whereDate('check_out_date', '>=', $dateString)
                    ->orWhere('status', Reservation::STATUS_IN_HOUSE);
            })
            ->with('guest')
            ->get()
            ->groupBy('room_id');

        $roomsData = $rooms->map(function (Room $room) use ($reservations, $dateString, $activeTickets): array {
            /** @var Collection<int, Reservation> $roomReservations */
            $roomReservations = $reservations->get($room->id, collect());

            $inHouseReservation = $roomReservations->first(
                fn (Reservation $reservation): bool => $reservation->status === Reservation::STATUS_IN_HOUSE,
            );

            $arrivalReservation = $roomReservations->first(
                fn (Reservation $reservation): bool => $reservation->status !== Reservation::STATUS_IN_HOUSE
                    && $reservation->status !== Reservation::STATUS_CANCELLED
                    && $reservation->status !== Reservation::STATUS_NO_SHOW
                    && $reservation->check_in_date?->toDateString() === $dateString,
            );

            $departureReservation = $roomReservations->first(
                fn (Reservation $reservation): bool => $reservation->status === Reservation::STATUS_IN_HOUSE
                    && $reservation->check_out_date?->toDateString() === $dateString,
            );

            $uiStatus = 'available';
            $currentReservation = null;
            $isOccupied = false;

            if ($room->status === Room::STATUS_OUT_OF_ORDER) {
                $uiStatus = 'out_of_order';
            } elseif ($room->status === Room::STATUS_OCCUPIED) {
                $uiStatus = 'occupied';
                $isOccupied = true;
                if ($inHouseReservation !== null) {
                    $currentReservation = $inHouseReservation;
                }
            } elseif ($room->status === 'inactive') {
                $uiStatus = 'inactive';
            } elseif ($inHouseReservation !== null) {
                $uiStatus = 'occupied';
                $currentReservation = $inHouseReservation;
                $isOccupied = true;
            } elseif ($arrivalReservation !== null) {
                $uiStatus = 'arrival_today';
                $currentReservation = $arrivalReservation;
            } elseif ($departureReservation !== null) {
                $uiStatus = 'departure_today';
                $currentReservation = $departureReservation;
            }

            $currentReservationSummary = null;

            if ($currentReservation instanceof Reservation) {
                $checkOutDate = $currentReservation->check_out_date
                    ? Carbon::parse($currentReservation->check_out_date)->toDateString()
                    : null;
                $isOverstay = $currentReservation->status === Reservation::STATUS_IN_HOUSE
                    && $checkOutDate !== null
                    && $checkOutDate < $dateString;

                $currentReservationSummary = [
                    'id' => $currentReservation->id,
                    'code' => $currentReservation->code,
                    'status' => $currentReservation->status,
                    'offer_id' => $currentReservation->offer_id,
                    'offer_name' => $currentReservation->offer_name,
                    'guest_name' => $currentReservation->guest?->name,
                    'check_in_date' => optional($currentReservation->check_in_date)->toDateString(),
                    'check_out_date' => optional($currentReservation->check_out_date)->toDateString(),
                    'check_in_at' => optional($currentReservation->check_in_date)->toDateTimeString(),
                    'check_out_at' => optional($currentReservation->check_out_date)->toDateTimeString(),
                    'unit_price' => (float) $currentReservation->unit_price,
                    'offer_kind' => $currentReservation->offer?->kind ?? $currentReservation->offer_kind ?? 'night',
                    'room_type_id' => $currentReservation->room_type_id,
                    'room_id' => $currentReservation->room_id,
                    'is_overstay' => $isOverstay,
                ];
            }

            $roomTickets = $activeTickets->get($room->id, collect());
            $blockingTickets = $roomTickets->where('blocks_sale', true);
            $activeTicket = $roomTickets->first();
            $isSellable = $blockingTickets->isEmpty()
                && ! $room->block_sale_after_checkout
                && ! in_array($room->status, [Room::STATUS_OUT_OF_ORDER, 'inactive'], true);

            return [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'room_type_name' => $room->roomType?->name,
                'status' => $room->status,
                'hk_status' => $room->hk_status,
                'ui_status' => $uiStatus,
                'is_occupied' => $isOccupied,
                'is_sellable' => $isSellable,
                'block_sale_after_checkout' => (bool) $room->block_sale_after_checkout,
                'maintenance_open_count' => $roomTickets->count(),
                'maintenance_blocking_count' => $blockingTickets->count(),
                'current_reservation' => $currentReservationSummary,
                'maintenance_tickets' => $roomTickets->map(function (MaintenanceTicket $ticket): array {
                    return [
                        'id' => $ticket->id,
                        'status' => $ticket->status,
                        'severity' => $ticket->severity,
                        'blocks_sale' => (bool) $ticket->blocks_sale,
                        'title' => $ticket->title,
                        'description' => $ticket->description,
                        'opened_at' => optional($ticket->opened_at)?->toDateTimeString(),
                        'assigned_to' => $ticket->assignedTo?->only(['id', 'name']),
                        'reported_by' => $ticket->reportedBy?->only(['id', 'name']),
                    ];
                })->values(),
                'maintenance_ticket' => $activeTicket ? [
                    'id' => $activeTicket->id,
                    'status' => $activeTicket->status,
                    'severity' => $activeTicket->severity,
                    'blocks_sale' => (bool) $activeTicket->blocks_sale,
                    'title' => $activeTicket->title,
                    'description' => $activeTicket->description,
                    'opened_at' => optional($activeTicket->opened_at)?->toDateTimeString(),
                    'assigned_to' => $activeTicket->assignedTo?->only(['id', 'name']),
                    'reported_by' => $activeTicket->reportedBy?->only(['id', 'name']),
                ] : null,
            ];
        });

        $roomsByFloor = $roomsData
            ->groupBy('floor')
            ->sortKeys()
            ->values();

        $walkInRoomId = (string) $request->query('room_id', '');
        $walkInSource = (string) $request->query('source', 'walk_in');

        $walkInRoom = null;
        $walkInRoomType = null;
        $walkInDefaultDates = null;
        $walkInOffers = [];

        if ($walkInRoomId !== '') {
            $room = Room::query()
                ->where('tenant_id', $tenantId)
                ->where('hotel_id', $hotelId)
                ->with('roomType')
                ->findOrFail($walkInRoomId);

            $checkInDate = $date->toDateString();
            $checkOutDate = $date->copy()->addDay()->toDateString();

            $prices = OfferRoomTypePrice::query()
                ->where('tenant_id', $tenantId)
                ->where('hotel_id', $hotelId)
                ->where('room_type_id', $room->room_type_id)
                ->where('is_active', true)
                ->with('offer')
                ->get();

            $walkInOffers = $prices->map(function (OfferRoomTypePrice $price): array {
                $offer = $price->offer;

                return [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'kind' => $offer->kind,
                    'price' => $price->price,
                    'offer_price_id' => $price->id,
                ];
            })->values();

            $walkInRoom = [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'room_type_id' => $room->room_type_id,
                'room_type_name' => optional($room->roomType)->name,
            ];

            /** @var RoomType|null $roomType */
            $roomType = $room->roomType;

            $walkInRoomType = $roomType ? [
                'id' => $roomType->id,
                'name' => $roomType->name,
                'capacity_adults' => $roomType->capacity_adults,
                'capacity_children' => $roomType->capacity_children,
            ] : null;

            $walkInDefaultDates = [
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate,
            ];
        }

        $guests = Guest::query()
            ->forTenant($tenantId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(200)
            ->get(['id', 'first_name', 'last_name', 'phone'])
            ->map(static function (Guest $guest): array {
                return [
                    'id' => $guest->id,
                    'first_name' => $guest->first_name,
                    'last_name' => $guest->last_name,
                    'phone' => $guest->phone,
                    'full_name' => trim(($guest->last_name ?? '').' '.($guest->first_name ?? '')),
                ];
            });

        $offers = Offer::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->orderBy('name')
            ->get(['id', 'name', 'kind', 'time_rule', 'time_config']);

        $offerRoomTypePrices = OfferRoomTypePrice::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->get(['room_type_id', 'offer_id', 'price', 'currency']);

        $paymentMethods = PaymentMethod::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) use ($hotelId): void {
                $query->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_default']);

        return [
            'date' => $dateString,
            'roomsByFloor' => $roomsByFloor,
            'walkInRoom' => $walkInRoom,
            'walkInRoomType' => $walkInRoomType,
            'walkInDefaultDates' => $walkInDefaultDates,
            'walkInOffers' => $walkInOffers,
            'walkInSource' => $walkInSource,
            'offers' => $offers,
            'offerRoomTypePrices' => $offerRoomTypePrices,
            'canManageHousekeeping' => $canManageHousekeeping,
            'maintenancePermissions' => [
                'canReport' => $user->can('maintenance_tickets.create'),
                'canHandle' => $user->can('maintenance_tickets.close'),
                'canProgress' => $user->can('maintenance_tickets.update'),
            ],
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'guests' => $guests,
            'paymentMethods' => $paymentMethods,
        ];
    }
}
