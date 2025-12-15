<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Services\OccupancyForecastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
    public function __construct(private readonly OccupancyForecastService $service) {}

    public function __invoke(Request $request): Response
    {
        Gate::authorize('night_audit.view');

        $user = $request->user();
        $hotelId = (int) ($request->integer('hotel_id') ?: ($user->active_hotel_id ?? $user->hotel_id));
        $days = (int) $request->integer('days', 7);

        $forecast = $this->service->generate((int) $user->tenant_id, $hotelId, $days);

        return Inertia::render('FrontDesk', [
            'reservationsData' => \App\Support\Frontdesk\ReservationsIndexData::build($request),
            'roomBoardData' => \App\Support\Frontdesk\RoomBoardData::build($request),
            'forecastData' => $forecast,
        ]);
    }
}
