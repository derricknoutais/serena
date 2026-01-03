<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\OfferTimeEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OfferTimeController extends Controller
{
    public function __construct(
        private readonly OfferTimeEngine $engine,
    ) {}

    public function preview(Request $request, Offer $offer): JsonResponse
    {
        $data = $request->validate([
            'arrival_at' => ['nullable', 'date'],
        ]);

        $offer->loadMissing('hotel');
        $timezone = $offer->hotel?->timezone ?? config('app.timezone');

        $arrivalAt = $data['arrival_at']
            ? Carbon::parse($data['arrival_at'], $timezone)->setTimezone($timezone)
            : now($timezone);

        $period = $this->engine->computeStayPeriod($offer, $arrivalAt);

        return response()->json([
            'arrival_at' => $period['arrival_at']->setTimezone($timezone)->format('Y-m-d\TH:i:s'),
            'departure_at' => $period['departure_at']->setTimezone($timezone)->format('Y-m-d\TH:i:s'),
        ]);
    }
}
