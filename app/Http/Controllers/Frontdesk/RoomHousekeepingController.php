<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\HousekeepingService;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoomHousekeepingController extends Controller
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly HousekeepingService $housekeepingService,
    ) {}

    public function updateStatus(Request $request, Room $room): JsonResponse
    {
        $data = $request->validate([
            'hk_status' => ['required', 'string', 'in:dirty,cleaning,awaiting_inspection,inspected,redo'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($room->tenant_id !== $user->tenant_id || $room->hotel_id !== $hotelId) {
            abort(403);
        }

        match ($data['hk_status']) {
            'inspected' => Gate::authorize('housekeeping.mark_inspected'),
            'dirty', 'redo' => Gate::authorize('housekeeping.mark_dirty'),
            default => Gate::authorize('housekeeping.mark_clean'),
        };

        $fromStatus = $room->hk_status;

        $this->housekeepingService->updateRoomStatus(
            $room,
            $data['hk_status'],
            $user,
            $data['note'] ?? null,
        );

        $this->notifier->notify('room.hk_status_updated', $room->hotel_id, [
            'tenant_id' => $room->tenant_id,
            'room_id' => $room->id,
            'room_number' => $room->number,
            'from_status' => $fromStatus,
            'to_status' => $room->hk_status,
            'user_name' => $user->name,
        ], [
            'cta_route' => 'rooms.board',
            'cta_params' => ['date' => now()->toDateString()],
        ]);

        return response()->json([
            'success' => true,
            'room' => [
                'id' => $room->id,
                'hk_status' => $room->hk_status,
            ],
        ]);
    }
}
