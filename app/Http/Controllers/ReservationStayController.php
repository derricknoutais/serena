<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\FolioBillingService;
use App\Services\HousekeepingPriorityService;
use App\Services\ReservationAvailabilityService;
use App\Services\ReservationConflictService;
use App\Services\RoomStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationStayController extends Controller
{
    public function __construct(
        private readonly ReservationAvailabilityService $availability,
        private readonly FolioBillingService $billingService,
        private readonly RoomStateMachine $roomStateMachine,
        private readonly ReservationConflictService $conflictService,
        private readonly HousekeepingPriorityService $priorityService,
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
            'offer_id' => [
                'nullable',
                'integer',
                Rule::exists('offers', 'id')
                    ->where('tenant_id', $reservation->tenant_id)
                    ->where('hotel_id', $reservation->hotel_id),
            ],
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

        $selectedOfferId = $data['offer_id'] ?? null;
        $useExtensionOffer = $action === 'extend'
            && $selectedOfferId !== null
            && (int) $selectedOfferId !== (int) ($reservation->offer_id ?? 0);

        if ($useExtensionOffer) {
            /** @var Offer $extensionOffer */
            $extensionOffer = Offer::query()
                ->where('tenant_id', $reservation->tenant_id)
                ->where('hotel_id', $reservation->hotel_id)
                ->findOrFail((int) $selectedOfferId);

            $extensionPrice = OfferRoomTypePrice::query()
                ->where('tenant_id', $reservation->tenant_id)
                ->where('hotel_id', $reservation->hotel_id)
                ->where('room_type_id', $reservation->room_type_id)
                ->where('offer_id', $extensionOffer->id)
                ->first();

            if (! $extensionPrice) {
                throw ValidationException::withMessages([
                    'offer_id' => 'Aucun tarif disponible pour cette offre et ce type de chambre.',
                ]);
            }

            $extensionUnitPrice = (float) $extensionPrice->price;
            $extensionQuantity = $extensionOffer->billing_mode === 'fixed'
                ? 1.0
                : $this->calculateStayQuantity(
                    $currentCheckOut,
                    $newCheckOut,
                    $extensionOffer->kind,
                    $this->resolveBundleNights($extensionOffer, $extensionOffer->kind),
                );
            $extensionAmount = $extensionOffer->billing_mode === 'fixed'
                ? $extensionUnitPrice
                : $extensionQuantity * $extensionUnitPrice;

            $reservation->check_out_date = $newCheckOut->toDateTimeString();
            $reservation->base_amount = $oldBaseAmount + $extensionAmount;
            $reservation->total_amount = $reservation->base_amount + (float) $reservation->tax_amount;
            $reservation->save();

            if (abs($extensionAmount) >= 0.01) {
                $description = 'Prolongation de séjour';
                $offerLabel = $extensionOffer->name ?? 'Séjour';
                $lineDescription = sprintf(
                    '%s - %s · Séjour du %s - %s',
                    $description,
                    $offerLabel,
                    $currentCheckOut->format('d/m/Y'),
                    $newCheckOut->format('d/m/Y'),
                );

                $this->billingService->addStayAdjustment($reservation, $extensionAmount, $description, [
                    'line_description' => $lineDescription,
                    'quantity' => $extensionQuantity,
                    'unit_price' => $extensionUnitPrice,
                    'meta' => [
                        'previous_check_out' => $currentCheckOut->toDateString(),
                        'new_check_out' => $newCheckOut->toDateString(),
                        'offer_id' => $extensionOffer->id,
                        'offer_name' => $extensionOffer->name,
                    ],
                ]);
            }

            if ($reservation->room_id) {
                $reservation->loadMissing('room');
                if ($reservation->room) {
                    $this->priorityService->syncRoomTasks($reservation->room, $request->user());
                }
            }

            return response()->json([
                'reservation' => $reservation->fresh(['room']),
                'base_amount' => $oldBaseAmount,
                'new_base_amount' => $reservation->base_amount,
                'delta' => $extensionAmount,
            ]);
        }

        $previousOffer = $reservation->offer;
        $previousOfferKind = $previousOffer?->kind ?? $reservation->offer_kind ?? 'night';
        if ($selectedOfferId) {
            /** @var Offer $offer */
            $offer = Offer::query()
                ->where('tenant_id', $reservation->tenant_id)
                ->where('hotel_id', $reservation->hotel_id)
                ->findOrFail((int) $selectedOfferId);

            $offerPrice = OfferRoomTypePrice::query()
                ->where('tenant_id', $reservation->tenant_id)
                ->where('hotel_id', $reservation->hotel_id)
                ->where('room_type_id', $reservation->room_type_id)
                ->where('offer_id', $offer->id)
                ->first();

            if (! $offerPrice) {
                throw ValidationException::withMessages([
                    'offer_id' => 'Aucun tarif disponible pour cette offre et ce type de chambre.',
                ]);
            }

            $reservation->offer_id = $offer->id;
            $reservation->offer_name = $offer->name;
            $reservation->offer_kind = $offer->kind;
            $reservation->unit_price = (float) $offerPrice->price;
            $reservation->currency = $offerPrice->currency ?? $reservation->currency;
            $reservation->setRelation('offer', $offer);
        }

        $previousQuantity = $this->calculateStayQuantity(
            $checkIn,
            $currentCheckOut,
            $previousOfferKind,
            $this->resolveBundleNights($previousOffer, $previousOfferKind),
        );
        $reservation->check_out_date = $newCheckOut->toDateTimeString();
        $currentOfferKind = $reservation->offer?->kind ?? $reservation->offer_kind ?? 'night';
        $quantity = $this->calculateStayQuantity(
            $checkIn,
            $newCheckOut,
            $currentOfferKind,
            $this->resolveBundleNights($reservation->offer, $currentOfferKind),
        );
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

        if ($reservation->room_id) {
            $reservation->loadMissing('room');
            if ($reservation->room) {
                $this->priorityService->syncRoomTasks($reservation->room, $request->user());
            }
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
        $currentOfferKind = $reservation->offer?->kind ?? $reservation->offer_kind ?? 'night';
        $quantity = $this->calculateStayQuantity(
            $checkIn,
            $checkOut,
            $currentOfferKind,
            $this->resolveBundleNights($reservation->offer, $currentOfferKind),
        );
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

        if ($previousRoom) {
            $this->priorityService->syncRoomTasks($previousRoom, $request->user());
        }

        $reservation->loadMissing('room');
        if ($reservation->room) {
            $this->priorityService->syncRoomTasks($reservation->room, $request->user());
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

    private function calculateStayQuantity(
        Carbon $checkIn,
        Carbon $checkOut,
        ?string $kind = null,
        int $bundleNights = 1,
    ): float {
        $kind = $kind ?? 'night';
        $minutes = max(1, $checkIn->diffInMinutes($checkOut));
        $nights = max(1, (int) ceil($minutes / 1440));

        return match ($kind) {
            'short_stay' => 1,
            'weekend', 'package' => max(1, (int) ceil($nights / max(1, $bundleNights))),
            default => $nights,
        };
    }

    private function resolveBundleNights(?Offer $offer, ?string $kind = null): int
    {
        $resolvedKind = $kind ?? $offer?->kind ?? 'night';
        if (! in_array($resolvedKind, ['weekend', 'package'], true)) {
            return 1;
        }

        if (! $offer) {
            return $resolvedKind === 'weekend' ? 2 : 1;
        }

        $bundle = 0;

        if ($offer->time_rule === 'weekend_window') {
            $bundle = (int) ($offer->time_config['checkout']['max_days_after_checkin'] ?? 0);
        } elseif ($offer->time_rule === 'fixed_checkout') {
            $bundle = (int) ($offer->time_config['day_offset'] ?? 0);
        } elseif ($offer->time_rule === 'rolling') {
            $minutes = (int) ($offer->time_config['duration_minutes'] ?? 0);
            $bundle = $minutes > 0 ? (int) ceil($minutes / 1440) : 0;
        } elseif ($offer->time_rule === 'fixed_window') {
            $startTime = $offer->time_config['start_time'] ?? null;
            $endTime = $offer->time_config['end_time'] ?? null;
            if (is_string($startTime) && is_string($endTime)) {
                [$startHour, $startMinute] = array_map('intval', explode(':', $startTime.':0'));
                [$endHour, $endMinute] = array_map('intval', explode(':', $endTime.':0'));
                $startMinutes = ($startHour * 60) + $startMinute;
                $endMinutes = ($endHour * 60) + $endMinute;
                if ($endMinutes <= $startMinutes) {
                    $endMinutes += 1440;
                }
                $duration = $endMinutes - $startMinutes;
                $bundle = $duration > 0 ? (int) ceil($duration / 1440) : 0;
            }
        }

        if ($bundle <= 0 && $offer->fixed_duration_hours !== null) {
            $bundle = (int) ceil(((int) $offer->fixed_duration_hours) / 24);
        }

        if ($bundle <= 0) {
            return $resolvedKind === 'weekend' ? 2 : 1;
        }

        return $bundle;
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
