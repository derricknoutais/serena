<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoomHousekeepingController extends Controller
{
    public function updateStatus(Request $request, Room $room): JsonResponse
    {
        $data = $request->validate([
            'hk_status' => ['required', 'string', 'in:dirty,clean,inspected'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($room->tenant_id !== $user->tenant_id || $room->hotel_id !== $hotelId) {
            abort(403);
        }

        match ($data['hk_status']) {
            'inspected' => Gate::authorize('housekeeping.mark_inspected'),
            'clean' => Gate::authorize('housekeeping.mark_clean'),
            'dirty' => Gate::authorize('housekeeping.mark_dirty'),
            default => null,
        };

        $fromStatus = $room->hk_status;
        $room->hk_status = $data['hk_status'];
        $room->save();

        activity('room')
            ->performedOn($room)
            ->causedBy($user)
            ->withProperties([
                'from_hk_status' => $fromStatus,
                'to_hk_status' => $room->hk_status,
                'room_number' => $room->number,
            ])
            ->event('hk_updated')
            ->log('hk_updated');

        return response()->json([
            'success' => true,
            'room' => [
                'id' => $room->id,
                'hk_status' => $room->hk_status,
            ],
        ]);
    }
}
