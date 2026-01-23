<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ChangeReservationRoomRequest;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\FolioBillingService;
use App\Services\HousekeepingPriorityService;
use App\Services\HousekeepingService;
use App\Services\ReservationAvailabilityService;
use App\Services\ReservationConflictService;
use App\Services\RoomStateMachine;
use App\Services\VapidEventNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        private readonly HousekeepingService $housekeepingService,
        private readonly VapidEventNotifier $vapidEventNotifier,
    ) {}

    public function updateDates(Request $request, Reservation $reservation): JsonResponse
    {
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
        $this->authorizeStayAction($action);

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
                $lineDescription = $this->formatExtensionLineDescription(
                    $description,
                    $offerLabel,
                    $currentCheckOut,
                    $newCheckOut,
                );

                $this->billingService->addStayExtensionItem(
                    $reservation,
                    $extensionAmount,
                    $currentCheckOut,
                    $newCheckOut,
                    [
                        'description' => $description,
                        'line_description' => $lineDescription,
                        'quantity' => $extensionQuantity,
                        'unit_price' => $extensionUnitPrice,
                        'offer_id' => $extensionOffer->id,
                        'offer_name' => $extensionOffer->name,
                        'offer_kind' => $extensionOffer->kind,
                        'meta' => [
                            'previous_check_out' => $currentCheckOut->toDateString(),
                            'new_check_out' => $newCheckOut->toDateString(),
                            'type_of_offer' => $extensionOffer->kind,
                            'period' => sprintf(
                                '%s - %s',
                                $currentCheckOut->toDateString(),
                                $newCheckOut->toDateString(),
                            ),
                        ],
                    ],
                );
            }

            if ($reservation->room_id) {
                $reservation->loadMissing('room');
                if ($reservation->room) {
                    $this->priorityService->syncRoomTasks($reservation->room, $request->user());
                }
            }

            $reservation->loadMissing('room');
            $this->vapidEventNotifier->notifyOwnersAndManagers(
                eventKey: 'reservation.extended',
                tenantId: (string) $reservation->tenant_id,
                hotelId: $reservation->hotel_id,
                title: 'Prolongation de séjour',
                body: sprintf(
                    'Réservation %s prolongée jusqu’au %s (Chambre %s).',
                    $reservation->code ?? '—',
                    $newCheckOut->toDateString(),
                    $reservation->room?->number ?? '—',
                ),
                url: route('frontdesk.reservations.details', ['reservation' => $reservation->id]),
                tag: 'reservation-extended',
            );

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

            if ($action === 'extend') {
                $lineDescription = $this->formatExtensionLineDescription(
                    $description,
                    $offerLabel,
                    $currentCheckOut,
                    $newCheckOut,
                );
                $extensionQuantity = $this->calculateStayQuantity(
                    $currentCheckOut,
                    $newCheckOut,
                    $currentOfferKind,
                    $this->resolveBundleNights($reservation->offer, $currentOfferKind),
                );

                $this->billingService->addStayExtensionItem(
                    $reservation,
                    $delta,
                    $currentCheckOut,
                    $newCheckOut,
                    [
                        'description' => $description,
                        'line_description' => $lineDescription,
                        'quantity' => $extensionQuantity,
                        'unit_price' => $unitPrice,
                        'offer_name' => $offerLabel,
                        'offer_kind' => $currentOfferKind,
                        'meta' => [
                            'previous_check_out' => $currentCheckOut->toDateString(),
                            'new_check_out' => $newCheckOut->toDateString(),
                            'offer_id' => $reservation->offer_id,
                        ],
                    ],
                );
            } else {
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
        }

        if ($action !== 'extend') {
            $this->billingService->syncStayChargeFromReservation($reservation);
        }

        if ($reservation->room_id) {
            $reservation->loadMissing('room');
            if ($reservation->room) {
                $this->priorityService->syncRoomTasks($reservation->room, $request->user());
            }
        }

        if ($action === 'extend') {
            $reservation->loadMissing('room');
            $this->vapidEventNotifier->notifyOwnersAndManagers(
                eventKey: 'reservation.extended',
                tenantId: (string) $reservation->tenant_id,
                hotelId: $reservation->hotel_id,
                title: 'Prolongation de séjour',
                body: sprintf(
                    'Réservation %s prolongée jusqu’au %s (Chambre %s).',
                    $reservation->code ?? '—',
                    $newCheckOut->toDateString(),
                    $reservation->room?->number ?? '—',
                ),
                url: route('frontdesk.reservations.details', ['reservation' => $reservation->id]),
                tag: 'reservation-extended',
            );
        }

        return response()->json([
            'reservation' => $reservation->fresh(['room']),
            'base_amount' => $oldBaseAmount,
            'new_base_amount' => $reservation->base_amount,
            'delta' => $delta,
        ]);
    }

    public function changeRoom(ChangeReservationRoomRequest $request, Reservation $reservation): JsonResponse
    {
        if (! Gate::check('reservations.change_room') && ! Gate::check('reservations.override_datetime')) {
            abort(403);
        }

        $this->ensureAuthorized($request, $reservation);
        $reservation->loadMissing(['offer', 'room.roomType']);

        if ($reservation->status !== Reservation::STATUS_IN_HOUSE) {
            throw ValidationException::withMessages([
                'reservation' => 'Cette réservation ne peut pas être modifiée.',
            ]);
        }

        $data = $request->validated();

        $previousRoom = $reservation->room;
        $vacatedUsage = $data['vacated_usage'] ?? null;
        $movedAt = isset($data['moved_at']) ? Carbon::parse($data['moved_at']) : now();

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

        if (in_array($newRoom->status, [Room::STATUS_OUT_OF_ORDER, 'inactive'], true)) {
            throw ValidationException::withMessages([
                'room_id' => 'La chambre sélectionnée n’est pas disponible.',
            ]);
        }

        if ($newRoom->isOccupiedNow()) {
            throw ValidationException::withMessages([
                'room_id' => 'La chambre sélectionnée est déjà occupée.',
            ]);
        }

        $checkIn = Carbon::parse($reservation->check_in_date);
        $checkOut = Carbon::parse($reservation->check_out_date);
        $availabilityStart = $movedAt->copy()->min($checkOut);

        $payload = [
            'tenant_id' => $reservation->tenant_id,
            'hotel_id' => $reservation->hotel_id,
            'room_type_id' => $newRoom->room_type_id,
            'room_id' => $newRoom->id,
            'status' => $reservation->status,
            'check_in_date' => $availabilityStart->toDateTimeString(),
            'check_out_date' => $checkOut->toDateTimeString(),
        ];

        $this->availability->ensureAvailable($payload, $reservation->id);
        $this->conflictService->validateOrThrowRoomConflict(
            $reservation->hotel_id,
            $newRoom->id,
            $availabilityStart,
            $checkOut,
            $reservation->id,
            $reservation->tenant_id,
        );

        return DB::transaction(function () use (
            $reservation,
            $previousRoom,
            $newRoom,
            $vacatedUsage,
            $checkIn,
            $checkOut,
            $movedAt,
            $request,
        ): JsonResponse {
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

            $pivotDate = $movedAt;

            $reservation->room_id = $newRoom->id;
            $reservation->room_type_id = $newRoom->room_type_id;
            $reservation->unit_price = $newUnitPrice;
            $reservation->base_amount = $newBaseAmount;
            $reservation->total_amount = $newBaseAmount + (float) $reservation->tax_amount;
            $reservation->save();

            $freshReservation = $reservation->fresh(['room', 'offer']);

            $pivotUsed = $this->billingService->resegmentStayForRoomChange(
                $freshReservation,
                $previousRoom,
                $newRoom,
                $pivotDate,
                $oldUnitPrice,
                $newUnitPrice,
                $vacatedUsage,
            ) ?? $pivotDate;

            $roomMoveDelta = $this->billingService->calculateRoomMoveDeltaAfterPivot(
                $freshReservation,
                $pivotUsed,
                $oldUnitPrice,
                $newUnitPrice,
            );

            $deltaAmount = (float) $roomMoveDelta['amount'];
            if (abs($deltaAmount) >= 0.01) {
                $folio = $this->billingService->ensureMainFolioForReservation($freshReservation);
                $adjustmentMeta = [
                    'kind' => 'room_move_delta',
                    'moved_at' => $pivotUsed->toDateTimeString(),
                    'old_room_id' => $previousRoom?->id,
                    'new_room_id' => $newRoom->id,
                    'from_price' => $oldUnitPrice,
                    'to_price' => $newUnitPrice,
                    'nights_after_pivot' => $roomMoveDelta['quantity'],
                ];

                $existingAdjustment = $folio->items()
                    ->where('type', 'stay_adjustment')
                    ->where('meta->kind', 'room_move_delta')
                    ->where('meta->moved_at', $adjustmentMeta['moved_at'])
                    ->where('meta->old_room_id', $adjustmentMeta['old_room_id'])
                    ->where('meta->new_room_id', $adjustmentMeta['new_room_id'])
                    ->exists();

                if (! $existingAdjustment) {
                    $description = sprintf(
                        'Ajustement changement de chambre (%s → %s) à partir du %s',
                        $previousRoom?->number ?? '—',
                        $newRoom->number ?? '—',
                        $pivotUsed->format('d/m H:i'),
                    );

                    $this->billingService->addStayAdjustment($freshReservation, $deltaAmount, 'Changement de chambre', [
                        'line_description' => $description,
                        'quantity' => 1,
                        'unit_price' => $deltaAmount,
                        'meta' => $adjustmentMeta,
                    ]);
                }
            }

            $previousRoomStatus = $previousRoom?->status;
            $previousRoomHkStatus = $previousRoom?->hk_status;

            if ($previousRoom) {
                $this->roomStateMachine->markAvailable($previousRoom);

                if ($vacatedUsage === 'used') {
                    $this->housekeepingService->forceRoomStatus($previousRoom, Room::HK_STATUS_DIRTY, $request->user());
                } elseif ($vacatedUsage === 'not_used') {
                    $this->housekeepingService->forceRoomStatus(
                        $previousRoom,
                        Room::HK_STATUS_INSPECTED,
                        $request->user(),
                    );
                } elseif ($vacatedUsage === 'unknown') {
                    $this->housekeepingService->forceRoomStatus(
                        $previousRoom,
                        Room::HK_STATUS_AWAITING_INSPECTION,
                        $request->user(),
                    );
                }
            }

            $assignedRoom = $freshReservation->room;

            if ($assignedRoom) {
                $this->roomStateMachine->markInUse($assignedRoom, $freshReservation);
                $this->housekeepingService->forceRoomStatus(
                    $assignedRoom,
                    Room::HK_STATUS_IN_USE,
                    $request->user(),
                );
            }

            $delta = $newBaseAmount - $oldBaseAmount;

            if ($previousRoom) {
                $this->priorityService->syncRoomTasks($previousRoom, $request->user());
            }

            $reservation->loadMissing('room');
            if ($reservation->room) {
                $this->priorityService->syncRoomTasks($reservation->room, $request->user());
            }

            activity('reservation')
                ->performedOn($reservation)
                ->causedBy($request->user())
                ->withProperties([
                    'reservation_code' => $reservation->code,
                    'old_room_id' => $previousRoom?->id,
                    'old_room_number' => $previousRoom?->number,
                    'new_room_id' => $newRoom->id,
                    'new_room_number' => $newRoom->number,
                    'vacated_usage' => $vacatedUsage,
                    'moved_at' => $pivotDate->toDateTimeString(),
                    'pivot_used' => $pivotUsed->toDateTimeString(),
                ])
                ->event('room_moved')
                ->log('room_moved');

            if ($previousRoom) {
                activity('room')
                    ->performedOn($previousRoom)
                    ->causedBy($request->user())
                    ->withProperties([
                        'room_number' => $previousRoom->number,
                        'from_status' => $previousRoomStatus,
                        'to_status' => $previousRoom->status,
                        'from_hk_status' => $previousRoomHkStatus,
                        'to_hk_status' => $previousRoom->hk_status,
                        'vacated_usage' => $vacatedUsage,
                    ])
                    ->event('room_move_vacated')
                    ->log('room_move_vacated');
            }

            activity('room')
                ->performedOn($assignedRoom ?? $newRoom)
                ->causedBy($request->user())
                ->withProperties([
                    'room_number' => $assignedRoom?->number ?? $newRoom->number,
                    'from_status' => $newRoom->getOriginal('status'),
                    'to_status' => $assignedRoom?->status ?? $newRoom->status,
                ])
                ->event('room_move_assigned')
                ->log('room_move_assigned');

            $this->vapidEventNotifier->notifyOwnersAndManagers(
                eventKey: 'reservation.room_moved',
                tenantId: (string) $reservation->tenant_id,
                hotelId: $reservation->hotel_id,
                title: 'Changement de chambre',
                body: sprintf(
                    'Réservation %s déplacée de %s vers %s.',
                    $reservation->code ?? '—',
                    $previousRoom?->number ?? '—',
                    $newRoom->number ?? '—',
                ),
                url: route('frontdesk.reservations.details', ['reservation' => $reservation->id]),
                tag: 'reservation-room-moved',
            );

            return response()->json([
                'reservation' => $reservation->fresh(['room']),
                'delta' => $delta,
                'room_move' => [
                    'old_room_id' => $previousRoom?->id,
                    'new_room_id' => $newRoom->id,
                    'vacated_usage' => $vacatedUsage,
                    'old_room_status' => $previousRoom?->status,
                    'old_room_hk_status' => $previousRoom?->hk_status,
                    'new_room_status' => $assignedRoom?->status ?? $newRoom->status,
                ],
            ]);
        });
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

    private function authorizeStayAction(string $action): void
    {
        $permission = match ($action) {
            'extend' => 'reservations.extend_stay',
            'shorten' => 'reservations.shorten_stay',
            default => 'reservations.override_datetime',
        };

        if (Gate::check($permission) || Gate::check('reservations.override_datetime')) {
            return;
        }

        abort(403);
    }

    private function formatExtensionLineDescription(
        string $description,
        string $offerLabel,
        Carbon $previousCheckOut,
        Carbon $newCheckOut,
    ): string {
        return sprintf(
            '%s - %s · Séjour du %s - %s',
            $description,
            $offerLabel,
            $previousCheckOut->format('d/m/Y'),
            $newCheckOut->format('d/m/Y'),
        );
    }
}
