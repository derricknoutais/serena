<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\FolioBillingService;
use App\Services\ReservationStateMachine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomBoardWalkInController extends Controller
{
    public function __construct(
        private readonly ReservationStateMachine $stateMachine,
        private readonly FolioBillingService $billingService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = (string) $user->tenant_id;
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        $validated = $request->validate([
            'guest_id' => ['required', 'integer', Rule::exists('guests', 'id')->where('tenant_id', $tenantId)],
            'room_id' => ['required', 'uuid', Rule::exists('rooms', 'id')->where('tenant_id', $tenantId)->where('hotel_id', $hotelId)],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'offer_id' => ['required', 'integer', Rule::exists('offers', 'id')->where('tenant_id', $tenantId)->where('hotel_id', $hotelId)],
            'offer_price_id' => ['required', 'integer', 'exists:offer_room_type_prices,id'],
            'check_in_at' => ['required', 'date'],
            'check_out_at' => ['required', 'date', 'after:check_in_at'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'amount_received' => ['required', 'numeric', 'min:0'],
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

        $guest = Guest::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($validated['guest_id']);

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

        $checkInAt = Carbon::parse($validated['check_in_at']);
        $checkOutAt = Carbon::parse($validated['check_out_at']);

        $checkInDate = $checkInAt->toDateString();
        $checkOutDate = $checkOutAt->toDateString();

        /** @var Reservation $reservation */
        $reservation = DB::transaction(function () use (
            $tenantId,
            $hotelId,
            $user,
            $guest,
            $room,
            $roomType,
            $offer,
            $unitPrice,
            $baseAmount,
            $taxAmount,
            $totalAmount,
            $checkInDate,
            $checkOutDate,
            $validated
        ): Reservation {
            /** @var Reservation $reservation */
            $reservation = Reservation::query()->create([
                'tenant_id' => $tenantId,
                'hotel_id' => $hotelId,
                'guest_id' => $guest->id,
                'room_type_id' => $roomType->id,
                'room_id' => $room->id,
                'offer_id' => $offer->id,
                'code' => $offer->code ?? null,
                'status' => Reservation::STATUS_PENDING,
                'source' => 'walk_in',
                'offer_name' => $offer->name,
                'offer_kind' => $offer->kind,
                'adults' => (int) $validated['adults'],
                'children' => (int) ($validated['children'] ?? 0),
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate,
                'currency' => $roomType->currency ?? 'XAF',
                'unit_price' => $unitPrice,
                'base_amount' => $baseAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'booked_by_user_id' => $user->id,
            ]);

            $this->stateMachine->confirm($reservation);
            $reservation = $this->stateMachine->checkIn($reservation);

            $amountReceived = (float) $validated['amount_received'];

            if ($amountReceived > 0.0) {
                $folio = $this->billingService->ensureMainFolioForReservation($reservation);

                $paymentMethod = PaymentMethod::query()
                    ->where('tenant_id', $tenantId)
                    ->where(function ($query) use ($hotelId): void {
                        $query->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
                    })
                    ->where('type', 'cash')
                    ->first();

                if (! $paymentMethod) {
                    throw ValidationException::withMessages([
                        'amount_received' => 'Aucun mode de paiement cash disponible.',
                    ]);
                }

                $cashSessionId = null;

                if ($paymentMethod->type === 'cash') {
                    $activeSession = \App\Models\CashSession::query()
                        ->where('tenant_id', $tenantId)
                        ->where('hotel_id', $hotelId)
                        ->where('type', 'frontdesk')
                        ->where('status', 'open')
                        ->first();

                    if (! $activeSession) {
                        throw ValidationException::withMessages([
                            'amount_received' => 'Aucune caisse réception ouverte. Veuillez ouvrir une session de caisse.',
                        ]);
                    }

                    $cashSessionId = $activeSession->id;
                }

                $folio->addPayment([
                    'amount' => $amountReceived,
                    'currency' => $folio->currency,
                    'payment_method_id' => $paymentMethod->id,
                    'paid_at' => now(),
                    'notes' => 'Paiement walk-in',
                    'created_by_user_id' => $user->id,
                    'cash_session_id' => $cashSessionId,
                ]);
            }

            return $reservation;
        });

        return response()->json([
            'reservation_id' => $reservation->id,
            'room_id' => $reservation->room_id,
            'status' => $reservation->status,
            'check_in_date' => $reservation->check_in_date?->toDateString(),
            'check_out_date' => $reservation->check_out_date?->toDateString(),
        ]);
    }
}
