<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Models\Offer;
use App\Models\Reservation;
use App\Services\FolioBillingService;
use App\Services\Offers\OfferReservationService;
use App\Services\ReservationAvailabilityService;
use App\Services\ReservationConflictService;
use App\Support\Frontdesk\ReservationsIndexData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
        StoreReservationRequest $request,
        ReservationAvailabilityService $availability,
        OfferReservationService $offerReservationService,
        ReservationConflictService $conflictService,
        \App\Services\Notifier $notifier,
    ) {
        $tenantId = $request->user()->tenant_id;
        $hotelId = $request->user()->active_hotel_id ?? $request->session()->get('active_hotel_id');

        $data = $request->validated();

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
        $data['status'] = $data['status'] ?? Reservation::STATUS_PENDING;
        $reservationDraft = new Reservation($data);
        if ($offer) {
            $reservationDraft->setRelation('offer', $offer);
        }

        $reservationDraft->validateOfferDates();

        $availability->ensureAvailable($data);
        if (! empty($data['room_id'])) {
            $conflictService->validateOrThrowRoomConflict(
                $hotelId,
                $data['room_id'],
                Carbon::parse($data['check_in_date']),
                Carbon::parse($data['check_out_date']),
                excludeReservationId: null,
                tenantId: $tenantId,
            );
        } elseif (! empty($data['room_type_id'])) {
            $conflictService->validateOrThrowOverbooking(
                $hotelId,
                (int) $data['room_type_id'],
                Carbon::parse($data['check_in_date']),
                Carbon::parse($data['check_out_date']),
                excludeReservationId: null,
                tenantId: $tenantId,
            );
        }

        $reservation = Reservation::query()->create($data);

        $this->logReservationActivity(
            event: 'created',
            reservation: $reservation,
            userId: $request->user()->id,
            properties: [
                'to_status' => $reservation->status,
                'check_in_date' => $reservation->check_in_date,
                'check_out_date' => $reservation->check_out_date,
                'total_amount' => $reservation->total_amount,
            ],
        );

        $notifier->notify('reservation.created', $hotelId, [
            'tenant_id' => $tenantId,
            'reservation_id' => $reservation->id,
            'reservation_code' => $reservation->code,
            'room_id' => $reservation->room_id,
            'room_number' => $reservation->room?->number,
            'guest_name' => $reservation->guest?->full_name ?? $reservation->guest?->name,
        ], [
            'cta_route' => 'reservations.show',
            'cta_params' => ['reservation' => $reservation->id],
        ]);

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Réservation créée.')
            ->with('newReservationId', $reservation->id);
    }

    public function update(
        UpdateReservationRequest $request,
        Reservation $reservation,
        FolioBillingService $billingService,
        ReservationAvailabilityService $availability,
        OfferReservationService $offerReservationService,
        ReservationConflictService $conflictService,
        \App\Services\Notifier $notifier,
    ) {
        $tenantId = $request->user()->tenant_id;

        abort_unless($reservation->tenant_id === $tenantId, 404);

        $data = $request->validated();

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
                        'status' => $reservation->status,
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
        if (! empty($data['room_id'])) {
            $conflictService->validateOrThrowRoomConflict(
                $reservation->hotel_id,
                $data['room_id'],
                Carbon::parse($data['check_in_date']),
                Carbon::parse($data['check_out_date']),
                $reservation->id,
                $reservation->tenant_id,
            );
        } elseif (! empty($data['room_type_id'])) {
            $conflictService->validateOrThrowOverbooking(
                $reservation->hotel_id,
                (int) $data['room_type_id'],
                Carbon::parse($data['check_in_date']),
                Carbon::parse($data['check_out_date']),
                $reservation->id,
                $reservation->tenant_id,
            );
        }

        $original = $reservation->getOriginal();
        $reservation->fill($data);
        $dirty = $reservation->getDirty();
        $reservation->save();

        if (in_array(
            $reservation->status,
            [Reservation::STATUS_CONFIRMED, Reservation::STATUS_IN_HOUSE],
            true,
        )) {
            $billingService->syncStayChargeFromReservation($reservation);
        }

        if (! empty($dirty)) {
            $this->logReservationActivity(
                event: 'updated',
                reservation: $reservation,
                userId: $request->user()->id,
                properties: [
                    'from_status' => $original['status'] ?? null,
                    'to_status' => $reservation->status,
                    'check_in_date' => $reservation->check_in_date,
                    'check_out_date' => $reservation->check_out_date,
                    'total_amount' => $reservation->total_amount,
                    'changes' => $this->formatChanges($dirty, $original, $reservation->getAttributes()),
                ],
            );
        }

        $notifier->notify('reservation.updated', $reservation->hotel_id, [
            'tenant_id' => $reservation->tenant_id,
            'reservation_id' => $reservation->id,
            'reservation_code' => $reservation->code,
            'room_id' => $reservation->room_id,
            'room_number' => $reservation->room?->number,
            'guest_name' => $reservation->guest?->full_name ?? $reservation->guest?->name,
        ], [
            'cta_route' => 'reservations.show',
            'cta_params' => ['reservation' => $reservation->id],
        ]);

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Réservation mise à jour.');
    }

    /**
     * @param  array<string, mixed>  $dirty
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $current
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function formatChanges(array $dirty, array $original, array $current): array
    {
        $changes = [];

        foreach (array_keys($dirty) as $key) {
            $changes[$key] = [
                'from' => $original[$key] ?? null,
                'to' => $current[$key] ?? null,
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logReservationActivity(string $event, Reservation $reservation, int $userId, array $properties = []): void
    {
        $reservation->loadMissing(['room', 'offer', 'guest']);

        $guestName = trim(sprintf(
            '%s %s',
            $reservation->guest?->first_name ?? '',
            $reservation->guest?->last_name ?? '',
        ));

        $baseProperties = [
            'reservation_code' => $reservation->code,
            'room_id' => $reservation->room_id,
            'room_number' => $reservation->room?->number,
            'offer_id' => $reservation->offer_id,
            'offer_name' => $reservation->offer?->name,
            'guest_id' => $reservation->guest_id,
            'guest_name' => Str::of($guestName)->trim()->value() ?: null,
        ];

        activity('reservation')
            ->performedOn($reservation)
            ->causedBy($userId)
            ->withProperties(array_filter($baseProperties + $properties, fn ($value) => $value !== null && $value !== ''))
            ->event($event)
            ->log($event);
    }
}
