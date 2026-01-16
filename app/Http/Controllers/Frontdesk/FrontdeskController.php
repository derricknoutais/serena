<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\User;
use App\Services\OccupancyForecastService;
use App\Support\Frontdesk\ReservationsIndexData;
use App\Support\Frontdesk\RoomBoardData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FrontdeskController extends Controller
{
    public function __construct(private readonly OccupancyForecastService $forecastService) {}

    public function dashboard(Request $request): Response
    {
        $forecast = null;
        if ($request->user()->can('night_audit.view')) {
            $forecast = $this->forecastService->generate(
                (int) $request->user()->tenant_id,
                (int) ($request->user()->active_hotel_id ?? $request->user()->hotel_id),
                7,
            );
        }

        return Inertia::render('FrontDesk', [
            'reservationsData' => ReservationsIndexData::build($request),
            'roomBoardData' => RoomBoardData::build($request),
            'forecastData' => $forecast,
        ]);
    }

    public function forecast(Request $request): JsonResponse
    {
        $request->validate([
            'days' => ['nullable', 'integer', 'in:7,14'],
            'hotel_id' => ['nullable', 'integer'],
        ]);

        if (! $request->user()->can('night_audit.view')) {
            abort(403);
        }

        $user = $request->user();
        $hotelId = (int) ($request->integer('hotel_id') ?: ($user->active_hotel_id ?? $user->hotel_id));
        $days = (int) $request->integer('days', 7);

        $forecast = $this->forecastService->generate((int) $user->tenant_id, $hotelId, $days);

        return response()->json($forecast);
    }

    public function arrivals(Request $request): JsonResponse
    {
        $reservations = $this->baseQuery($request)
            ->whereDate('check_in_date', Carbon::today())
            ->whereIn('status', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_CONFIRMED,
            ])
            ->get();

        return response()->json(['data' => $reservations->map(fn ($reservation) => $this->transform($reservation, $request->user()))]);
    }

    public function departures(Request $request): JsonResponse
    {
        $reservations = $this->baseQuery($request)
            ->whereDate('check_out_date', Carbon::today())
            ->where('status', Reservation::STATUS_IN_HOUSE)
            ->get();

        return response()->json(['data' => $reservations->map(fn ($reservation) => $this->transform($reservation, $request->user()))]);
    }

    public function inHouse(Request $request): JsonResponse
    {
        $reservations = $this->baseQuery($request)
            ->where('status', Reservation::STATUS_IN_HOUSE)
            ->get();

        return response()->json(['data' => $reservations->map(fn ($reservation) => $this->transform($reservation, $request->user()))]);
    }

    public function issues(Request $request): JsonResponse
    {
        $reservations = $this->baseQuery($request)
            ->where('status', Reservation::STATUS_IN_HOUSE)
            ->whereDate('check_out_date', '<', Carbon::today())
            ->get();

        return response()->json(['data' => $reservations->map(fn ($reservation) => $this->transform($reservation, $request->user()))]);
    }

    private function baseQuery(Request $request)
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $hotelId = $user->active_hotel_id;

        return Reservation::query()
            ->where('tenant_id', $tenantId)
            ->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))
            ->with([
                'guest:id,first_name,last_name',
                'room:id,number',
                'roomType:id,name',
                'offer:id,name',
            ])
            ->orderBy('check_in_date');
    }

    private function transform(Reservation $reservation, User $user): array
    {
        $guest = $reservation->guest;
        $room = $reservation->room;
        $roomType = $reservation->roomType;
        $offer = $reservation->offer;

        return [
            'id' => $reservation->id,
            'guest' => [
                'name' => trim(($guest->first_name ?? '').' '.($guest->last_name ?? '')) ?: '—',
            ],
            'room' => [
                'number' => $room?->number,
                'type' => $roomType?->name,
                'hk_status' => $room?->hk_status,
            ],
            'offer' => [
                'name' => $offer?->name ?? $reservation->offer_name,
            ],
            'check_in_date' => $reservation->check_in_date?->toDateString(),
            'check_out_date' => $reservation->check_out_date?->toDateString(),
            'status' => $reservation->status,
            'status_label' => $reservation->status_label,
            'actions' => $this->availableActions($reservation, $user),
        ];
    }

    private function availableActions(Reservation $reservation, User $user): array
    {
        $map = [
            'confirm' => Reservation::STATUS_CONFIRMED,
            'check_in' => Reservation::STATUS_IN_HOUSE,
            'check_out' => Reservation::STATUS_CHECKED_OUT,
            'cancel' => Reservation::STATUS_CANCELLED,
            'no_show' => Reservation::STATUS_NO_SHOW,
        ];

        return collect($map)
            ->filter(function ($status, $action) use ($reservation, $user): bool {
                if (! $reservation->canTransition($status)) {
                    return false;
                }

                $permission = match ($action) {
                    'confirm' => 'reservations.confirm',
                    'check_in' => 'reservations.check_in',
                    'check_out' => 'reservations.check_out',
                    'cancel' => 'reservations.cancel',
                    'no_show' => 'reservations.force_status',
                    default => 'reservations.force_status',
                };

                return $user?->can($permission) ?? false;
            })
            ->keys()
            ->values()
            ->all();
    }
}
