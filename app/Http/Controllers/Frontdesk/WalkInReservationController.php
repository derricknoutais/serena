<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WalkInReservationController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();
        $tenantId = (string) $user->tenant_id;
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        $roomId = (string) $request->query('room_id', '');

        abort_if($roomId === '', 404);

        $dateParam = $request->query('date');
        $date = $dateParam ? Carbon::parse((string) $dateParam) : Carbon::today();

        $source = (string) $request->query('source', 'walk_in');

        $room = Room::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->with('roomType')
            ->findOrFail($roomId);

        $checkInDate = $date->toDateString();
        $checkOutDate = $date->copy()->addDay()->toDateString();

        $prices = OfferRoomTypePrice::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $room->room_type_id)
            ->where('is_active', true)
            ->with('offer')
            ->get();

        $offers = $prices->map(function (OfferRoomTypePrice $price): array {
            /** @var Offer $offer */
            $offer = $price->offer;

            return [
                'id' => $offer->id,
                'name' => $offer->name,
                'kind' => $offer->kind,
                'price' => $price->price,
                'offer_price_id' => $price->id,
            ];
        })->values();

        return Inertia::render('Frontdesk/Reservations/WalkInCreate', [
            'room' => [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'room_type_id' => $room->room_type_id,
                'room_type_name' => optional($room->roomType)->name,
            ],
            'roomType' => [
                'id' => $room->room_type_id,
                'name' => optional($room->roomType)->name,
                'capacity_adults' => optional($room->roomType)->capacity_adults,
                'capacity_children' => optional($room->roomType)->capacity_children,
            ],
            'defaultDates' => [
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate,
            ],
            'offers' => $offers,
            'source' => $source,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $tenantId = (string) $user->tenant_id;
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        $validated = $request->validate([
            'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'guest_first_name' => ['required_without:guest_id', 'string', 'max:255'],
            'guest_last_name' => ['required_without:guest_id', 'string', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:50'],

            'room_id' => ['required', 'uuid', 'exists:rooms,id'],
            'room_type_id' => ['required', 'integer'],

            'offer_id' => ['required', 'integer'],
            'offer_price_id' => ['required', 'integer'],

            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],

            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
        ]);

        $room = Room::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->findOrFail($validated['room_id']);

        /** @var RoomType $roomType */
        $roomType = RoomType::query()->findOrFail((int) $validated['room_type_id']);

        $totalGuests = (int) $validated['adults'] + (int) ($validated['children'] ?? 0);
        $maxGuests = (int) ($roomType->capacity_adults ?? 0) + (int) ($roomType->capacity_children ?? 0);

        if ($maxGuests > 0 && $totalGuests > $maxGuests) {
            throw ValidationException::withMessages([
                'adults' => 'La capacité maximale de la chambre est dépassée.',
            ]);
        }

        $guestId = $validated['guest_id'] ?? null;

        if ($guestId === null) {
            /** @var Guest $guest */
            $guest = Guest::query()->create([
                'tenant_id' => $tenantId,
                'first_name' => $validated['guest_first_name'] ?? '',
                'last_name' => $validated['guest_last_name'] ?? '',
                'phone' => $validated['guest_phone'] ?? null,
            ]);

            $guestId = $guest->id;
        }

        /** @var OfferRoomTypePrice $offerPrice */
        $offerPrice = OfferRoomTypePrice::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('id', (int) $validated['offer_price_id'])
            ->with('offer')
            ->firstOrFail();

        /** @var Offer $offer */
        $offer = $offerPrice->offer;

        $unitPrice = (float) $offerPrice->price;
        $baseAmount = $unitPrice;
        $taxAmount = 0.0;
        $totalAmount = $baseAmount + $taxAmount;

        $checkInDate = Carbon::parse($validated['check_in_date'])->toDateString();
        $checkOutDate = Carbon::parse($validated['check_out_date'])->toDateString();

        /** @var Reservation $reservation */
        $reservation = DB::transaction(function () use (
            $tenantId,
            $hotelId,
            $guestId,
            $roomType,
            $room,
            $offer,
            $unitPrice,
            $baseAmount,
            $taxAmount,
            $totalAmount,
            $checkInDate,
            $checkOutDate,
            $validated
        ): Reservation {
            $reservationCode = Reservation::generateCode($tenantId, Carbon::parse($checkInDate));

            return Reservation::query()->create([
                'tenant_id' => $tenantId,
                'hotel_id' => $hotelId,
                'guest_id' => $guestId,
                'room_type_id' => $roomType->id,
                'room_id' => $room->id,
                'offer_id' => $offer->id,
                'code' => $reservationCode,
                'status' => Reservation::STATUS_CONFIRMED,
                'source' => 'walk_in',
                'offer_name' => $offer->name,
                'offer_kind' => $offer->kind,
                'adults' => (int) $validated['adults'],
                'children' => (int) ($validated['children'] ?? 0),
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate,
                'actual_check_in_at' => null,
                'currency' => $roomType->currency ?? 'XAF',
                'unit_price' => $unitPrice,
                'base_amount' => $baseAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ]);
        });

        return redirect()
            ->route('rooms.board', ['date' => $checkInDate])
            ->with('success', 'Réservation walk-in créée et client enregistré en chambre.');
    }
}
