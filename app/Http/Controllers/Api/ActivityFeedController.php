<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityFeedController extends Controller
{
    public function reservation(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeReservation($request, $reservation);

        $reservation->loadMissing('hotel');

        $timezone = $reservation->hotel?->timezone ?? config('app.timezone');

        $activities = Activity::query()
            ->where('log_name', 'reservation')
            ->where('subject_type', Reservation::class)
            ->where('subject_id', (string) $reservation->id)
            ->where(function ($query) use ($reservation): void {
                $query->whereNull('properties->reservation_code')
                    ->orWhere('properties->reservation_code', $reservation->code);
            })
            ->where(function ($query) use ($reservation): void {
                $query->whereNull('hotel_id')
                    ->orWhere('hotel_id', (int) $reservation->hotel_id);
            })
            ->latest('created_at')
            ->limit(50)
            ->with('causer')
            ->get()
            ->map(fn (Activity $activity): array => $this->transformActivity($activity, $timezone));

        return response()->json($activities);
    }

    public function room(Request $request, Room $room): JsonResponse
    {
        $this->authorizeRoom($request, $room);

        $room->loadMissing('hotel');

        $timezone = $room->hotel?->timezone ?? config('app.timezone');

        $activities = Activity::query()
            ->where('log_name', 'room')
            ->where('subject_type', Room::class)
            ->where('subject_id', (string) $room->id)
            ->where(function ($query) use ($room): void {
                $query->whereNull('hotel_id')
                    ->orWhere('hotel_id', (int) $room->hotel_id);
            })
            ->latest('created_at')
            ->limit(50)
            ->with('causer')
            ->get()
            ->map(fn (Activity $activity): array => $this->transformActivity($activity, $timezone));

        return response()->json($activities);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole(['owner', 'manager'])) {
            abort(403);
        }

        $tenantId = (string) $user->tenant_id;
        $hotelId = (int) ($request->query('hotel_id', $user->active_hotel_id ?? $user->hotel_id ?? 0));

        $query = Activity::query()
            ->where('tenant_id', $tenantId)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId));

        if ($logName = $request->query('log_name')) {
            $query->where('log_name', $logName);
        }

        if ($causerId = $request->query('causer_id')) {
            $query->where('causer_id', (string) $causerId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($q = $request->query('q')) {
            $query->where(function ($inner) use ($q): void {
                $inner->where('description', 'like', '%'.$q.'%')
                    ->orWhere('event', 'like', '%'.$q.'%')
                    ->orWhereJsonContains('properties->reservation_code', $q)
                    ->orWhereJsonContains('properties->room_number', $q);
            });
        }

        $activities = $query
            ->with('causer')
            ->latest('created_at')
            ->paginate(50);

        return response()->json($activities);
    }

    private function authorizeReservation(Request $request, Reservation $reservation): void
    {
        $user = $request->user();

        if ($reservation->tenant_id !== $user->tenant_id) {
            abort(403);
        }
    }

    private function authorizeRoom(Request $request, Room $room): void
    {
        $user = $request->user();

        if ($room->tenant_id !== $user->tenant_id) {
            abort(403);
        }
    }

    /**
     * @return array{
     *     id: int|string,
     *     description: ?string,
     *     event: ?string,
     *     created_at: ?string,
     *     properties: array<string, mixed>,
     *     causer: ?array{id: int|string, name: string, email: string},
     * }
     */
    private function transformActivity(Activity $activity, string $timezone): array
    {
        $createdAt = $activity->created_at?->copy()->setTimezone($timezone);

        return [
            'id' => $activity->id,
            'description' => $activity->description,
            'event' => $activity->event,
            'created_at' => $createdAt?->format('d/m/Y H:i'),
            'properties' => $activity->properties?->toArray() ?? [],
            'causer' => $activity->causer
                ? [
                    'id' => $activity->causer->getKey(),
                    'name' => $activity->causer->name,
                    'email' => $activity->causer->email,
                ]
                : null,
        ];
    }
}
