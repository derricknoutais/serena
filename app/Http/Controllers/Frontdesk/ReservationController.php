<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Reservation;
use App\Services\FolioBillingService;
use App\Services\Offers\OfferReservationService;
use App\Services\ReservationAvailabilityService;
use App\Support\Frontdesk\ReservationsIndexData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Frontdesk/Reservations/ReservationsIndex', ReservationsIndexData::build($request));
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

    public function store(
        Request $request,
        ReservationAvailabilityService $availability,
        OfferReservationService $offerReservationService,
    ) {
        $tenantId = $request->user()->tenant_id;
        $hotelId = $request->user()->active_hotel_id ?? $request->session()->get('active_hotel_id');

        $data = $request->validate([
            'code' => ['required', 'string'],
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
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

        $offer = null;
        if (! empty($data['offer_id'])) {
            $offer = Offer::query()
                ->where('tenant_id', $tenantId)
                ->where('hotel_id', $hotelId)
                ->find($data['offer_id']);
        }

        if ($offer && $data['room_id']) {
            $startAt = Carbon::parse($data['check_in_date']);
            $endAt = Carbon::parse($data['check_out_date']);

            try {
                $draftFromOffer = $offerReservationService->buildReservationFromOffer(
                    $offer,
                    $startAt,
                    $data['room_id'],
                    $endAt,
                    [
                        'tenant_id' => $tenantId,
                        'hotel_id' => $hotelId,
                        'guest_id' => $data['guest_id'],
                        'code' => $data['code'],
                        'status' => $data['status'],
                        'notes' => $data['notes'] ?? null,
                        'booked_by_user_id' => $request->user()->id,
                        'currency' => $data['currency'],
                        'unit_price' => $data['unit_price'],
                        'base_amount' => $data['base_amount'],
                        'tax_amount' => $data['tax_amount'],
                        'total_amount' => $data['total_amount'],
                        'adults' => $data['adults'] ?? 1,
                        'children' => $data['children'] ?? 0,
                        'source' => $data['source'] ?? null,
                        'expected_arrival_time' => $data['expected_arrival_time'] ?? null,
                    ],
                );

                $data['check_in_date'] = $draftFromOffer->check_in_date;
                $data['check_out_date'] = $draftFromOffer->check_out_date;
                $data['offer_name'] = $draftFromOffer->offer_name;
                $data['offer_kind'] = $draftFromOffer->offer_kind;
            } catch (\App\Exceptions\OfferNotValidForDateTimeException $e) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'offer_id' => $e->getMessage(),
                ]);
            }
        } elseif ($offer) {
            $data['offer_name'] = $offer->name;
            $data['offer_kind'] = $offer->kind;
        }

        $data['tenant_id'] = $tenantId;
        $data['hotel_id'] = $hotelId;
        $data['booked_by_user_id'] = $request->user()->id;
        $data['adults'] = $data['adults'] ?? 1;
        $data['children'] = $data['children'] ?? 0;
        $reservationDraft = new Reservation($data);
        if ($offer) {
            $reservationDraft->setRelation('offer', $offer);
        }

        $reservationDraft->validateOfferDates();

        $availability->ensureAvailable($data);

        $reservation = Reservation::query()->create($data);

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Réservation créée.')
            ->with('newReservationId', $reservation->id);
    }

    public function update(
        Request $request,
        Reservation $reservation,
        FolioBillingService $billingService,
        ReservationAvailabilityService $availability,
        OfferReservationService $offerReservationService,
    ) {
        $tenantId = $request->user()->tenant_id;

        abort_unless($reservation->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'code' => ['required', 'string'],
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'room_id' => ['nullable', 'uuid', 'exists:rooms,id'],
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

        $offer = null;
        if (! empty($data['offer_id'])) {
            $offer = Offer::query()
                ->where('tenant_id', $reservation->tenant_id)
                ->where('hotel_id', $reservation->hotel_id)
                ->find($data['offer_id']);
        }

        if ($offer && $data['room_id']) {
            $startAt = Carbon::parse($data['check_in_date']);
            $endAt = Carbon::parse($data['check_out_date']);

            try {
                $draftFromOffer = $offerReservationService->buildReservationFromOffer(
                    $offer,
                    $startAt,
                    $data['room_id'],
                    $endAt,
                    [
                        'tenant_id' => $reservation->tenant_id,
                        'hotel_id' => $reservation->hotel_id,
                        'guest_id' => $data['guest_id'],
                        'code' => $data['code'],
                        'status' => $data['status'],
                        'notes' => $data['notes'] ?? null,
                        'booked_by_user_id' => $reservation->booked_by_user_id ?? $request->user()->id,
                        'currency' => $data['currency'],
                        'unit_price' => $data['unit_price'],
                        'base_amount' => $data['base_amount'],
                        'tax_amount' => $data['tax_amount'],
                        'total_amount' => $data['total_amount'],
                        'adults' => $data['adults'] ?? 1,
                        'children' => $data['children'] ?? 0,
                        'source' => $data['source'] ?? null,
                        'expected_arrival_time' => $data['expected_arrival_time'] ?? null,
                    ],
                );

                $data['check_in_date'] = $draftFromOffer->check_in_date;
                $data['check_out_date'] = $draftFromOffer->check_out_date;
                $data['offer_name'] = $draftFromOffer->offer_name;
                $data['offer_kind'] = $draftFromOffer->offer_kind;
            } catch (\App\Exceptions\OfferNotValidForDateTimeException $e) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'offer_id' => $e->getMessage(),
                ]);
            }
        } elseif ($offer) {
            $data['offer_name'] = $offer->name;
            $data['offer_kind'] = $offer->kind;
        }

        $data['adults'] = $data['adults'] ?? 1;
        $data['children'] = $data['children'] ?? 0;
        $data['tenant_id'] = $reservation->tenant_id;
        $data['hotel_id'] = $reservation->hotel_id;

        $draft = $reservation->replicate();
        $draft->fill($data);
        if ($offer) {
            $draft->setRelation('offer', $offer);
        } elseif ($reservation->relationLoaded('offer')) {
            $draft->setRelation('offer', $reservation->getRelation('offer'));
        }

        $draft->validateOfferDates();

        $availability->ensureAvailable($data, $reservation->id);

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
}
