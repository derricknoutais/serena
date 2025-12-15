<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\OfferNotValidForDateTimeException;
use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\Offers\OfferReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationFromOfferController extends Controller
{
    public function __construct(private readonly OfferReservationService $offerReservationService) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = (string) $user->tenant_id;
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        $data = $request->validate([
            'offer_id' => [
                'required',
                'integer',
                Rule::exists('offers', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $hotelId),
            ],
            'room_id' => [
                'required',
                'uuid',
                Rule::exists('rooms', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $hotelId),
            ],
            'guest_id' => ['nullable', 'integer', Rule::exists('guests', 'id')->where('tenant_id', $tenantId)],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(Reservation::statuses())],
            'code' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'base_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        /** @var Offer $offer */
        $offer = Offer::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->findOrFail($data['offer_id']);

        /** @var Room $room */
        $room = Room::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->findOrFail($data['room_id']);

        $startAt = isset($data['start_at'])
            ? Carbon::parse($data['start_at'])
            : Carbon::now();

        $customEnd = isset($data['end_at'])
            ? Carbon::parse($data['end_at'])
            : null;

        $baseAttributes = [
            'tenant_id' => $tenantId,
            'hotel_id' => $hotelId,
            'guest_id' => $data['guest_id'] ?? null,
            'room_type_id' => $room->room_type_id,
            'code' => $data['code'] ?? null,
            'status' => $data['status'] ?? Reservation::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
            'booked_by_user_id' => $user->id,
            'currency' => $room->roomType?->currency ?? $room->hotel?->currency ?? 'XAF',
            'unit_price' => $data['unit_price'] ?? 0,
            'base_amount' => $data['base_amount'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'total_amount' => $data['total_amount'] ?? 0,
        ];

        try {
            $reservation = $this->offerReservationService->buildReservationFromOffer(
                $offer,
                $startAt,
                $room->id,
                $customEnd,
                $baseAttributes,
            );
        } catch (OfferNotValidForDateTimeException $e) {
            throw ValidationException::withMessages([
                'offer_id' => $e->getMessage(),
            ]);
        }

        $reservation->save();

        $reservation->loadMissing(['room', 'offer', 'guest']);

        activity('reservation')
            ->performedOn($reservation)
            ->causedBy($user)
            ->withProperties([
                'reservation_code' => $reservation->code,
                'room_id' => $reservation->room_id,
                'room_number' => $reservation->room?->number,
                'offer_id' => $reservation->offer_id,
                'offer_name' => $reservation->offer?->name,
                'guest_id' => $reservation->guest_id,
                'guest_name' => $reservation->guest?->first_name
                    ? trim($reservation->guest->first_name.' '.$reservation->guest->last_name)
                    : null,
                'to_status' => $reservation->status,
                'check_in_date' => $reservation->check_in_date,
                'check_out_date' => $reservation->check_out_date,
                'total_amount' => $reservation->total_amount,
            ])
            ->event('created')
            ->log('created');

        return response()->json([
            'reservation' => [
                'id' => $reservation->id,
                'room_id' => $reservation->room_id,
                'offer_id' => $reservation->offer_id,
                'status' => $reservation->status,
                'check_in_date' => $reservation->check_in_date?->toDateString(),
                'check_out_date' => $reservation->check_out_date?->toDateString(),
            ],
        ]);
    }
}
