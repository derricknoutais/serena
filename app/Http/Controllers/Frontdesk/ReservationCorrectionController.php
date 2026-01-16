<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeReservationGuestRequest;
use App\Http\Requests\ChangeReservationOfferRequest;
use App\Http\Requests\OverrideReservationStayTimesRequest;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Offers\OfferReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReservationCorrectionController extends Controller
{
    public function changeGuest(
        ChangeReservationGuestRequest $request,
        Reservation $reservation,
    ): JsonResponse {
        $this->authorize('reservations.change_guest');

        /** @var User $user */
        $user = $request->user();

        $this->ensureTenantScope($reservation, $user);

        $oldGuestId = $reservation->guest_id;
        $reservation->update([
            'guest_id' => $request->validated('guest_id'),
        ]);

        activity('reservation')
            ->performedOn($reservation)
            ->causedBy($user)
            ->withProperties([
                'from_guest_id' => $oldGuestId,
                'to_guest_id' => $reservation->guest_id,
                'reservation_code' => $reservation->code,
            ])
            ->event('guest_changed')
            ->log('reservation.guest_changed');

        return response()->json([
            'success' => true,
            'reservation_id' => $reservation->id,
            'guest_id' => $reservation->guest_id,
        ]);
    }

    public function changeOffer(
        ChangeReservationOfferRequest $request,
        Reservation $reservation,
        OfferReservationService $offerReservationService,
    ): JsonResponse {
        $this->authorize('reservations.change_offer');

        /** @var User $user */
        $user = $request->user();

        $this->ensureTenantScope($reservation, $user);

        $data = $request->validated();
        $offer = Offer::query()
            ->where('tenant_id', $reservation->tenant_id)
            ->where('hotel_id', $reservation->hotel_id)
            ->findOrFail($data['offer_id']);

        $oldOfferId = $reservation->offer_id;
        $oldOfferName = $reservation->offer_name;
        $oldOfferKind = $reservation->offer_kind;

        if ($reservation->room_id && $reservation->check_in_date) {
            try {
                $draft = $offerReservationService->buildReservationFromOffer(
                    $offer,
                    Carbon::parse($reservation->check_in_date),
                    $reservation->room_id,
                    $reservation->check_out_date ? Carbon::parse($reservation->check_out_date) : null,
                    [
                        'tenant_id' => $reservation->tenant_id,
                        'hotel_id' => $reservation->hotel_id,
                        'guest_id' => $reservation->guest_id,
                    ],
                );

                $reservation->check_in_date = $draft->check_in_date;
                $reservation->check_out_date = $draft->check_out_date;
                $reservation->offer_name = $draft->offer_name;
                $reservation->offer_kind = $draft->offer_kind;
            } catch (\App\Exceptions\OfferNotValidForDateTimeException $exception) {
                throw ValidationException::withMessages([
                    'offer_id' => $exception->getMessage(),
                ]);
            }
        } else {
            $reservation->offer_name = $offer->name;
            $reservation->offer_kind = $offer->kind;
        }

        $reservation->offer_id = $offer->id;
        $reservation->save();

        activity('reservation')
            ->performedOn($reservation)
            ->causedBy($user)
            ->withProperties([
                'from_offer_id' => $oldOfferId,
                'to_offer_id' => $reservation->offer_id,
                'from_offer_name' => $oldOfferName,
                'to_offer_name' => $reservation->offer_name,
                'from_offer_kind' => $oldOfferKind,
                'to_offer_kind' => $reservation->offer_kind,
                'reservation_code' => $reservation->code,
            ])
            ->event('offer_changed')
            ->log('reservation.offer_changed');

        return response()->json([
            'success' => true,
            'reservation_id' => $reservation->id,
            'offer_id' => $reservation->offer_id,
        ]);
    }

    public function overrideStayTimes(
        OverrideReservationStayTimesRequest $request,
        Reservation $reservation,
    ): JsonResponse {
        $this->authorize('reservations.override_stay_times');

        /** @var User $user */
        $user = $request->user();

        $this->ensureTenantScope($reservation, $user);

        if ($reservation->status === Reservation::STATUS_CHECKED_OUT) {
            abort(422, 'La réservation est déjà clôturée.');
        }

        $data = $request->validated();
        $reason = $data['reason'] ?? null;

        if (! $user->hasRole(['owner', 'manager', 'superadmin'])) {
            $touchesPast = collect([
                $data['check_in_date'] ?? null,
                $data['check_out_date'] ?? null,
                $data['actual_check_in_at'] ?? null,
                $data['actual_check_out_at'] ?? null,
            ])->filter()->contains(function ($date): bool {
                return Carbon::parse($date)->isPast();
            });

            if ($touchesPast && ! $reason) {
                throw ValidationException::withMessages([
                    'reason' => 'Merci de préciser la raison de la modification.',
                ]);
            }
        }

        $payload = array_filter([
            'check_in_date' => $data['check_in_date'] ?? null,
            'check_out_date' => $data['check_out_date'] ?? null,
            'actual_check_in_at' => $data['actual_check_in_at'] ?? null,
            'actual_check_out_at' => $data['actual_check_out_at'] ?? null,
        ], fn ($value) => $value !== null);

        $reservation->update($payload);

        activity('reservation')
            ->performedOn($reservation)
            ->causedBy($user)
            ->withProperties([
                'reservation_code' => $reservation->code,
                'reason' => $reason,
                'check_in_date' => $reservation->check_in_date,
                'check_out_date' => $reservation->check_out_date,
                'actual_check_in_at' => $reservation->actual_check_in_at,
                'actual_check_out_at' => $reservation->actual_check_out_at,
            ])
            ->event('times_overridden')
            ->log('reservation.times_overridden');

        return response()->json([
            'success' => true,
            'reservation_id' => $reservation->id,
        ]);
    }

    private function ensureTenantScope(Reservation $reservation, User $user): void
    {
        if ($reservation->tenant_id !== $user->tenant_id) {
            abort(404);
        }

        if ((int) $reservation->hotel_id !== (int) ($user->active_hotel_id ?? $user->hotel_id)) {
            abort(404);
        }
    }
}
