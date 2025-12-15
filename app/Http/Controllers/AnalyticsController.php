<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeRole($request);

        $user = $request->user();
        $hotelId = (int) ($request->integer('hotel_id') ?: ($user->active_hotel_id ?? $user->hotel_id));

        return Inertia::render('Analytics/Index', [
            'defaultHotelId' => $hotelId,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $user = $request->user();
        $hotelId = (int) ($request->integer('hotel_id') ?: ($user->active_hotel_id ?? $user->hotel_id));
        abort_if($hotelId === 0, 404, 'Hôtel introuvable.');

        [$from, $to] = $this->dateRange($request);

        $data = $this->analyticsService->summary((string) $user->tenant_id, $hotelId, $from, $to);

        return response()->json($data);
    }

    public function trends(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $user = $request->user();
        $hotelId = (int) ($request->integer('hotel_id') ?: ($user->active_hotel_id ?? $user->hotel_id));
        abort_if($hotelId === 0, 404, 'Hôtel introuvable.');

        [$from, $to] = $this->dateRange($request);

        $data = $this->analyticsService->trends((string) $user->tenant_id, $hotelId, $from, $to);

        return response()->json($data);
    }

    public function payments(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $user = $request->user();
        $hotelId = (int) ($request->integer('hotel_id') ?: ($user->active_hotel_id ?? $user->hotel_id));
        abort_if($hotelId === 0, 404, 'Hôtel introuvable.');

        [$from, $to] = $this->dateRange($request);

        $data = [
            'by_method' => $this->analyticsService->paymentsByMethod((string) $user->tenant_id, $hotelId, $from, $to),
            'cash_sessions' => $this->analyticsService->cashDifferences((string) $user->tenant_id, $hotelId, $from, $to),
        ];

        return response()->json($data);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $user = $request->user();
        $hotelId = (int) ($request->integer('hotel_id') ?: ($user->active_hotel_id ?? $user->hotel_id));
        abort_if($hotelId === 0, 404, 'Hôtel introuvable.');

        [$from, $to] = $this->dateRange($request);
        $limit = min(max((int) $request->integer('limit', 5), 1), 10);

        $data = [
            'products' => $this->analyticsService->topProducts((string) $user->tenant_id, $hotelId, $from, $to, $limit),
            'guests' => $this->analyticsService->topGuests((string) $user->tenant_id, $hotelId, $from, $to, 10),
        ];

        return response()->json($data);
    }

    private function dateRange(Request $request): array
    {
        $from = Carbon::parse($request->input('from', now()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }

    private function authorizeRole(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->hasAnyRole(['owner', 'manager', 'superadmin']), 403);
    }
}
