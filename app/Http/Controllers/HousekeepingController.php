<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HousekeepingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $roomId = (string) $request->query('room', '');
        $room = null;

        if ($roomId !== '') {
            $room = Room::query()->find($roomId);
            if ($room) {
                $room = $this->roomPayload($room);
            }
        }

        return Inertia::render('Housekeeping/Index', [
            'room' => $room,
            'canManageHousekeeping' => $this->canManage($request),
        ]);
    }

    public function show(Request $request, Room $room): JsonResponse
    {
        $this->authorizeAccess($request);

        return response()->json([
            'room' => $this->roomPayload($room),
        ]);
    }

    public function updateStatus(Request $request, Room $room): JsonResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'hk_status' => ['required', 'in:clean,dirty,inspected'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $room->hk_status = $data['hk_status'];
        $room->save();

        // Optionally log note in future.

        return response()->json([
            'room' => $this->roomPayload($room),
        ]);
    }

    private function roomPayload(Room $room): array
    {
        $room->loadMissing('roomType');

        $today = Carbon::today();
        $currentReservation = Reservation::query()
            ->where('room_id', $room->id)
            ->whereDate('check_out_date', '>=', $today)
            ->whereIn('status', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_IN_HOUSE,
            ])
            ->with('guest')
            ->orderBy('check_in_date')
            ->first();

        $occupancyState = 'Libre';
        if ($currentReservation) {
            $occupancyState = match ($currentReservation->status) {
                Reservation::STATUS_IN_HOUSE => 'En séjour',
                Reservation::STATUS_CONFIRMED, Reservation::STATUS_PENDING => $currentReservation->check_in_date?->isToday()
                    ? 'Arrivée aujourd’hui'
                    : 'Réservation future',
                default => 'Réservation en cours',
            };

            if ($currentReservation->check_out_date?->isToday()) {
                $occupancyState = 'Départ aujourd’hui';
            }
        }

        return [
            'id' => $room->id,
            'number' => $room->number,
            'floor' => $room->floor,
            'room_type' => $room->roomType?->name,
            'hk_status' => $room->hk_status,
            'hk_status_label' => $this->hkStatusLabel($room->hk_status),
            'occupancy' => [
                'state' => $occupancyState,
                'reservation' => $currentReservation ? [
                    'id' => $currentReservation->id,
                    'code' => $currentReservation->code,
                    'status' => $currentReservation->status,
                    'guest_name' => $currentReservation->guest
                        ? trim(($currentReservation->guest->first_name ?? '').' '.($currentReservation->guest->last_name ?? ''))
                        : null,
                    'check_in_date' => optional($currentReservation->check_in_date)->toDateString(),
                    'check_out_date' => optional($currentReservation->check_out_date)->toDateString(),
                ] : null,
            ],
        ];
    }

    private function hkStatusLabel(string $status): string
    {
        return match ($status) {
            'dirty' => 'Sale',
            'inspected' => 'Inspectée',
            default => 'Propre',
        };
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user->hasRole(['owner', 'manager', 'housekeeping', 'superadmin']);
    }
}
