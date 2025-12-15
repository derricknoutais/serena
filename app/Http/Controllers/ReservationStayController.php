<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OfferRoomTypePrice;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\FolioBillingService;
use App\Services\ReservationAvailabilityService;
use App\Services\ReservationConflictService;
use App\Services\RoomStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReservationStayController extends Controller
{
    public function __construct(
        private readonly ReservationAvailabilityService $availability,
        private readonly FolioBillingService $billingService,
        private readonly RoomStateMachine $roomStateMachine,
        private readonly ReservationConflictService $conflictService,
    ) {}

    public function updateDates(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('reservations.override_datetime');
        $this->ensureAuthorized($request, $reservation);
        $reservation->loadMissing(['offer', 'room.roomType', 'mainFolio']);

        if (! in_array($reservation->status, [Reservation::STATUS_CONFIRMED, Reservation::STATUS_IN_HOUSE], true)) {
            throw ValidationException::withMessages([
                'reservation' => 'Cette réservation ne peut pas être modifiée.',
            ]);
        }

        if (! $reservation->check_in_date || ! $reservation->check_out_date) {
            throw ValidationException::withMessages([
                'check_out_date' => 'Chaînes de dates invalides.',
            ]);
        }

        $data = $request->validate([
            'check_out_date' => ['required', 'date'],
        ]);

        $newCheckOut = Carbon::parse($data['check_out_date']);
        $currentCheckOut = Carbon::parse($reservation->check_out_date);
        $checkIn = Carbon::parse($reservation->check_in_date);

        if ($newCheckOut->lessThanOrEqualTo($checkIn)) {
            throw ValidationException::withMessages([
                'check_out_date' => 'La nouvelle date doit être postérieure à la date d’arrivée.',
            ]);
        }

        if ($newCheckOut->equalTo($currentCheckOut)) {
            throw ValidationException::withMessages([
                'check_out_date' => 'La date de départ doit changer.',
            ]);
        }

        $action = $newCheckOut->greaterThan($currentCheckOut) ? 'extend' : 'shorten';

        $payload = [
            'tenant_id' => $reservation->tenant_id,
            'hotel_id' => $reservation->hotel_id,
            'room_type_id' => $reservation->room_type_id,
            'room_id' => $reservation->room_id,
            'status' => $reservation->status,
            'check_in_date' => $checkIn->toDateTimeString(),
            'check_out_date' => $newCheckOut->toDateTimeString(),
        ];

        if ($reservation->room_id) {
            $this->conflictService->validateOrThrowRoomConflict(
                $reservation->hotel_id,
                $reservation->room_id,
                $checkIn,
                $newCheckOut,
                $reservation->id,
                $reservation->tenant_id,
            );
        } elseif ($reservation->room_type_id) {
            $this->conflictService->validateOrThrowOverbooking(
                $reservation->hotel_id,
                (int) $reservation->room_type_id,
                $checkIn,
                $newCheckOut,
                $reservation->id,
                $reservation->tenant_id,
            );
        }

        $this->availability->ensureAvailable($payload, $reservation->id);

        $oldBaseAmount = (float) $reservation->base_amount;
        $oldTotalAmount = (float) $reservation->total_amount;

        $previousQuantity = $this->calculateStayQuantity($reservation, $checkIn, $currentCheckOut);
        $reservation->check_out_date = $newCheckOut->toDateTimeString();
        $quantity = $this->calculateStayQuantity($reservation, $checkIn, $newCheckOut);
        $unitPrice = (float) $reservation->unit_price;
        $reservation->base_amount = $quantity * $unitPrice;
        $reservation->total_amount = $reservation->base_amount + (float) $reservation->tax_amount;
        $reservation->save();

        $delta = $reservation->base_amount - $oldBaseAmount;
        $quantityDelta = $quantity - $previousQuantity;

        if (abs($delta) >= 0.01) {
            $description = $action === 'extend' ? 'Prolongation de séjour' : 'Réduction de séjour';
            $offerLabel = $reservation->offer?->name ?? $reservation->offer_name ?? 'Séjour';
            $lineDescription = sprintf(
                '%s - %s · Séjour du %s - %s',
                $description,
                $offerLabel,
                $currentCheckOut->format('d/m/Y'),
                $newCheckOut->format('d/m/Y'),
            );

            $this->billingService->addStayAdjustment($reservation, $delta, $description, [
                'line_description' => $lineDescription,
                'quantity' => abs($quantityDelta),
                'unit_price' => $unitPrice * ($quantityDelta >= 0 ? 1 : -1),
                'meta' => [
                    'previous_check_out' => $currentCheckOut->toDateString(),
                    'new_check_out' => $newCheckOut->toDateString(),
                ],
            ]);
        }

        return response()->json([
            'reservation' => $reservation->fresh(['room']),
            'base_amount' => $oldBaseAmount,
            'new_base_amount' => $reservation->base_amount,
            'delta' => $delta,
        ]);
    }

    public function changeRoom(Request $request, Reservation $reservation): JsonResponse
    {
        $this->ensureAuthorized($request, $reservation);
        $reservation->loadMissing(['offer', 'room.roomType']);

        if (! in_array($reservation->status, [Reservation::STATUS_CONFIRMED, Reservation::STATUS_IN_HOUSE], true)) {
            throw ValidationException::withMessages([
                'reservation' => 'Cette réservation ne peut pas être modifiée.',
            ]);
        }

        $data = $request->validate([
            'room_id' => ['required', 'uuid', 'exists:rooms,id'],
        ]);

        $previousRoom = $reservation->room;

        $newRoom = Room::query()
            ->where('tenant_id', $reservation->tenant_id)
            ->where('hotel_id', $reservation->hotel_id)
            ->findOrFail($data['room_id']);
        $newRoom->loadMissing('roomType');

        if ($reservation->room_id === $newRoom->id) {
            throw ValidationException::withMessages([
                'room_id' => 'La chambre sélectionnée est identique à la chambre actuelle.',
            ]);
        }

        $checkIn = Carbon::parse($reservation->check_in_date);
        $checkOut = Carbon::parse($reservation->check_out_date);

        $payload = [
            'tenant_id' => $reservation->tenant_id,
            'hotel_id' => $reservation->hotel_id,
            'room_type_id' => $newRoom->room_type_id,
            'room_id' => $newRoom->id,
            'status' => $reservation->status,
            'check_in_date' => $checkIn->toDateString(),
            'check_out_date' => $checkOut->toDateString(),
        ];

        $this->availability->ensureAvailable($payload, $reservation->id);
        $this->conflictService->validateOrThrowRoomConflict(
            $reservation->hotel_id,
            $newRoom->id,
            $checkIn,
            $checkOut,
            $reservation->id,
            $reservation->tenant_id,
        );

        $oldBaseAmount = (float) $reservation->base_amount;
        $oldUnitPrice = (float) $reservation->unit_price;

        $newUnitPrice = $this->determineUnitPrice($reservation, $newRoom->room_type_id) ?? $oldUnitPrice;
        $quantity = $this->calculateStayQuantity($reservation, $checkIn, $checkOut);
        $newBaseAmount = $quantity * $newUnitPrice;

        $pivotDate = Carbon::now();

        $reservation->room_id = $newRoom->id;
        $reservation->room_type_id = $newRoom->room_type_id;
        $reservation->unit_price = $newUnitPrice;
        $reservation->base_amount = $newBaseAmount;
        $reservation->total_amount = $newBaseAmount + (float) $reservation->tax_amount;
        $reservation->save();

        if ($reservation->status === Reservation::STATUS_IN_HOUSE) {
            $freshReservation = $reservation->fresh(['room']);

            $this->billingService->resegmentStayForRoomChange(
                $freshReservation,
                $previousRoom,
                $newRoom,
                $pivotDate,
                $oldUnitPrice,
                $newUnitPrice,
            );

            if ($previousRoom) {
                $this->roomStateMachine->markAvailable($previousRoom);
            }

            if ($freshReservation->room) {
                $this->roomStateMachine->markOccupied($freshReservation->room, $freshReservation);
            }
        }

        $delta = $newBaseAmount - $oldBaseAmount;

        if (abs($delta) >= 0.01) {
            $this->billingService->addStayAdjustment($reservation, $delta, 'Changement de chambre');
        }

        return response()->json([
            'reservation' => $reservation->fresh(['room']),
            'delta' => $delta,
        ]);
    }

    private function ensureAuthorized(Request $request, Reservation $reservation): void
    {
        abort_unless($reservation->tenant_id === $request->user()->tenant_id, 403);
    }

    private function calculateStayQuantity(Reservation $reservation, Carbon $checkIn, Carbon $checkOut): float
    {
        $kind = $reservation->offer?->kind ?? $reservation->offer_kind ?? 'night';
        $nights = max(1, $checkIn->diffInDays($checkOut));

        return match ($kind) {
            'short_stay' => 1,
            'weekend' => max(2, $nights),
            default => $nights,
        };
    }

    private function determineUnitPrice(Reservation $reservation, int $roomTypeId): ?float
    {
        if (! $reservation->offer_id) {
            return null;
        }

        return (float) OfferRoomTypePrice::query()
            ->where('tenant_id', $reservation->tenant_id)
            ->where('hotel_id', $reservation->hotel_id)
            ->where('room_type_id', $roomTypeId)
            ->where('offer_id', $reservation->offer_id)
            ->value('price');
    }
}
