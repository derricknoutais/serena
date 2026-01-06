<?php

declare(strict_types=1);

namespace App\Support\Frontdesk;

use App\Models\Guest;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskChecklistItem;
use App\Models\MaintenanceTicket;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\HousekeepingPriorityService;
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
        $canManageHousekeeping = $user->can('housekeeping.mark_clean')
            || $user->can('housekeeping.mark_dirty')
            || $user->can('housekeeping.mark_inspected');

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        app(HousekeepingPriorityService::class)->syncHotelTasks($tenantId, $hotelId);

        $dateParam = $request->query('date');
        $date = $dateParam ? Carbon::parse((string) $dateParam)->startOfDay() : now()->startOfDay();
        $dateString = $date->toDateString();

        $rooms = Room::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->with(['roomType', 'hotel:id,timezone'])
            ->orderBy('id')
            ->get();

        $inspectionTasks = HousekeepingTask::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('type', HousekeepingTask::TYPE_INSPECTION)
            ->where('status', HousekeepingTask::STATUS_DONE)
            ->whereIn('room_id', $rooms->pluck('id'))
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('room_id')
            ->map(fn ($tasks) => $tasks->first());

        $failedInspectionIds = $inspectionTasks
            ->filter(fn (HousekeepingTask $task): bool => $task->outcome === HousekeepingTask::OUTCOME_FAILED)
            ->map(fn (HousekeepingTask $task): int|string => $task->id)
            ->values()
            ->all();

        $failedInspectionRemarks = HousekeepingTaskChecklistItem::query()
            ->when($failedInspectionIds === [], fn ($query) => $query->whereRaw('1 = 0'))
            ->whereIn('task_id', $failedInspectionIds)
            ->with('checklistItem:id,label')
            ->get()
            ->groupBy('task_id');

        $openTasks = HousekeepingTask::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereIn('room_id', $rooms->pluck('id'))
            ->whereIn('status', [
                HousekeepingTask::STATUS_PENDING,
                HousekeepingTask::STATUS_IN_PROGRESS,
            ])
            ->orderByRaw("case status when 'in_progress' then 0 else 1 end")
            ->orderByRaw("case type when 'inspection' then 0 else 1 end")
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('room_id')
            ->map(fn ($tasks) => $tasks->first());

        $priorityService = app(HousekeepingPriorityService::class);

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

        $roomsData = $rooms->map(function (Room $room) use (
            $reservations,
            $dateString,
            $activeTickets,
            $inspectionTasks,
            $failedInspectionRemarks,
            $openTasks,
            $priorityService
        ): array {
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

            $arrivalToday = $arrivalReservation !== null;
            $openTask = $openTasks->get($room->id);

            if ($openTask) {
                $openTask->setRelation('room', $room);
            }

            $hkPriority = $openTask
                ? $priorityService->computePriorityForTask($openTask, $arrivalToday)
                : $priorityService->computePriorityForRoom($room, $arrivalToday);

            $hkSort = self::hkSortWeight($room->hk_status, $arrivalToday);

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
                    'check_in_at' => self::formatDateTimeLocal($currentReservation->check_in_date),
                    'check_out_at' => self::formatDateTimeLocal($currentReservation->check_out_date),
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
                && $room->hk_status === Room::HK_STATUS_INSPECTED
                && ! in_array($room->status, [Room::STATUS_OUT_OF_ORDER, 'inactive'], true);

            $lastInspectionTask = $inspectionTasks->get($room->id);
            $inspectionRemarks = null;

            if ($lastInspectionTask && $lastInspectionTask->outcome === HousekeepingTask::OUTCOME_FAILED) {
                $inspectionRemarks = $failedInspectionRemarks
                    ->get($lastInspectionTask->id, collect())
                    ->filter(fn ($item): bool => ! $item->is_ok && (string) $item->note !== '')
                    ->map(fn ($item): array => [
                        'label' => $item->checklistItem?->label,
                        'note' => $item->note,
                    ])
                    ->values()
                    ->all();
            }

            return [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'room_type_name' => $room->roomType?->name,
                'status' => $room->status,
                'hk_status' => $room->hk_status,
                'hk_priority' => $hkPriority,
                'arrival_today' => $arrivalToday,
                'hk_sort' => $hkSort,
                'ui_status' => $uiStatus,
                'is_occupied' => $isOccupied,
                'is_sellable' => $isSellable,
                'last_inspection' => $lastInspectionTask ? [
                    'outcome' => $lastInspectionTask->outcome,
                    'ended_at' => self::formatDateTimeLocal($lastInspectionTask->ended_at),
                    'remarks' => $inspectionRemarks,
                ] : null,
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
            ->map(fn (Collection $rooms): Collection => $rooms->values())
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

    private static function hkSortWeight(?string $status, bool $arrivalToday): int
    {
        return match ($status) {
            Room::HK_STATUS_REDO => 1,
            Room::HK_STATUS_AWAITING_INSPECTION => $arrivalToday ? 2 : 5,
            Room::HK_STATUS_DIRTY => $arrivalToday ? 3 : 4,
            Room::HK_STATUS_CLEANING => 6,
            Room::HK_STATUS_INSPECTED => 7,
            default => 99,
        };
    }

    private static function formatDateTimeLocal(?Carbon $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value->format('Y-m-d\TH:i:s');
    }
}
