<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\OfferTimeEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $arrivalAt = $data['arrival_at']
            ? now()->parse($data['arrival_at'])
            : now();

        $period = $this->engine->computeStayPeriod($offer, $arrivalAt);

        return response()->json([
            'arrival_at' => $period['arrival_at']->toIso8601String(),
            'departure_at' => $period['departure_at']->toIso8601String(),
        ]);
    }
}
