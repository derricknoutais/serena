<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\FolioBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
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
            ->orderBy('check_in_date')
            ->limit(200)
            ->get()
            ->map(function (Reservation $reservation) {
                $start = $reservation->actual_check_in_at ?? ($reservation->check_in_date ? Carbon::parse($reservation->check_in_date) : null);
                $endBase = $reservation->actual_check_out_at ?? ($reservation->check_out_date ? Carbon::parse($reservation->check_out_date) : null);
                $end = $endBase?->copy()->addDay();

                return [
                    'id' => $reservation->id,
                    'title' => $reservation->code,
                    'start' => $start?->toDateString(),
                    'end' => $end?->toDateString(),
                    'status' => $reservation->status,
                    'guest_id' => $reservation->guest_id,
                    'room_type_id' => $reservation->room_type_id,
                    'room_id' => $reservation->room_id,
                    'offer_id' => $reservation->offer_id,
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
                    'check_in_date' => $reservation->check_in_date?->toDateString(),
                    'check_out_date' => $reservation->check_out_date?->toDateString(),
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
            ->get(['id', 'name', 'code', 'kind']);

        $offerRoomTypePrices = OfferRoomTypePrice::query()
            ->where('tenant_id', $tenantId)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->get(['room_type_id', 'offer_id', 'price', 'currency']);

        return Inertia::render('Frontdesk/Reservations/ReservationsIndex', [
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
            'canManageTimes' => $user->hasRole(['owner', 'manager']),
        ]);
    }

    public function show(Reservation $reservation): Response
    {
        $reservation->load(['guest', 'room', 'roomType']);

        return Inertia::render('Reservations/Show', [
            'reservation' => [
                'id' => $reservation->id,
                'code' => $reservation->code,
                'status' => $reservation->status,
                'guest' => $reservation->guest,
                'room' => $reservation->room,
                'room_type' => $reservation->roomType,
                'check_in_date' => $reservation->check_in_date,
                'check_out_date' => $reservation->check_out_date,
                'total_amount' => $reservation->total_amount,
                'currency' => $reservation->currency,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $hotelId = $request->user()->active_hotel_id ?? $request->session()->get('active_hotel_id');

        $data = $request->validate([
            'code' => ['required', 'string'],
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'offer_id' => ['nullable', 'integer', 'exists:offers,id'],
            'status' => ['required', 'string'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'base_amount' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'adults' => ['nullable', 'integer', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:255'],
            'expected_arrival_time' => ['nullable', 'date_format:H:i'],
        ]);

        $data['tenant_id'] = $tenantId;
        $data['hotel_id'] = $hotelId;
        $data['booked_by_user_id'] = $request->user()->id;
        $data['adults'] = $data['adults'] ?? 1;
        $data['children'] = $data['children'] ?? 0;

        $reservation = Reservation::query()->create($data);

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Réservation créée.')
            ->with('newReservationId', $reservation->id);
    }

    public function update(Request $request, Reservation $reservation, FolioBillingService $billingService)
    {
        $tenantId = $request->user()->tenant_id;

        abort_unless($reservation->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'code' => ['required', 'string'],
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'offer_id' => ['nullable', 'integer', 'exists:offers,id'],
            'status' => ['required', 'string'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'base_amount' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'adults' => ['nullable', 'integer', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:255'],
            'expected_arrival_time' => ['nullable', 'date_format:H:i'],
        ]);

        $data['adults'] = $data['adults'] ?? 1;
        $data['children'] = $data['children'] ?? 0;

        $reservation->update($data);

        if (in_array(
            $reservation->status,
            [Reservation::STATUS_CONFIRMED, Reservation::STATUS_IN_HOUSE],
            true,
        )) {
            $billingService->syncStayChargeFromReservation($reservation);
        }

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Réservation mise à jour.');
    }

    public function updateStatus(Request $request, Reservation $reservation, FolioBillingService $billingService)
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        abort_unless($reservation->tenant_id === $tenantId, 404);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:confirm,check_in,check_out'],
            'expected_arrival_time' => ['nullable', 'date_format:H:i'],
            'event_datetime' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);

        $action = $validated['action'];
        $canManageTimes = $user->hasRole(['owner', 'manager']);

        if ($action === 'confirm' && $reservation->status === Reservation::STATUS_PENDING) {
            $reservation->status = Reservation::STATUS_CONFIRMED;

            if ($canManageTimes && ! empty($validated['expected_arrival_time'])) {
                $reservation->expected_arrival_time = $validated['expected_arrival_time'];
            } elseif (! $reservation->expected_arrival_time) {
                $reservation->expected_arrival_time = now()->format('H:i');
            }
        }

        if (
            $action === 'check_in'
            && in_array(
                $reservation->status,
                [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED],
                true,
            )
        ) {
            $reservation->status = Reservation::STATUS_IN_HOUSE;

            if ($canManageTimes && ! empty($validated['event_datetime'])) {
                $reservation->actual_check_in_at = Carbon::createFromFormat(
                    'Y-m-d\TH:i',
                    $validated['event_datetime'],
                );
            } else {
                $reservation->actual_check_in_at = now();
            }

            $billingService->ensureMainFolioForReservation($reservation);
            $billingService->syncStayChargeFromReservation($reservation);
        }

        if ($action === 'check_out' && $reservation->status === Reservation::STATUS_IN_HOUSE) {
            $reservation->status = Reservation::STATUS_CHECKED_OUT;

            if ($canManageTimes && ! empty($validated['event_datetime'])) {
                $reservation->actual_check_out_at = Carbon::createFromFormat(
                    'Y-m-d\TH:i',
                    $validated['event_datetime'],
                );
            } else {
                $reservation->actual_check_out_at = now();
            }
        }

        $reservation->save();

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Statut mis à jour.');
    }
}
