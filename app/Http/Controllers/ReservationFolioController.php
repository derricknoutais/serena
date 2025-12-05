<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\FolioBillingService;
use App\Services\FolioPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationFolioController extends Controller
{
    public function __construct(private readonly FolioPayloadService $folioPayloads) {}

    public function show(Request $request, Reservation $reservation, FolioBillingService $billingService): JsonResponse
    {
        abort_unless(
            (string) $reservation->tenant_id === (string) $request->user()->tenant_id,
            404
        );

        $folio = $billingService->ensureMainFolioForReservation($reservation);

        $payload = $this->folioPayloads->make($folio, $reservation, $request->user());

        return response()->json($payload);
    }
}
