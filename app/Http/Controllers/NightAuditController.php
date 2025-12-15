<?php

namespace App\Http\Controllers;

use App\Services\NightAuditService;
use App\Services\Notifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NightAuditController extends Controller
{
    public function __construct(
        private readonly NightAuditService $nightAuditService,
        private readonly Notifier $notifier,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('night_audit.view');

        $user = $request->user();
        $hotelId = (int) ($request->integer('hotel_id') ?: ($user->active_hotel_id ?? $user->hotel_id));

        abort_if($hotelId === 0, 404, 'Hôtel introuvable.');

        $businessDate = $this->businessDateFromRequest($request);

        $report = $this->nightAuditService->cachedGenerate(
            (int) $user->tenant_id,
            $hotelId,
            $businessDate,
            $request->boolean('refresh'),
        );

        if ($request->boolean('refresh')) {
            $this->notifier->notify('business_day.closed', $hotelId, [
                'tenant_id' => $user->tenant_id,
                'business_date' => $businessDate->toDateString(),
            ], [
                'cta_route' => 'night-audit.index',
                'cta_params' => ['date' => $businessDate->toDateString()],
            ]);
        }

        return Inertia::render('NightAudit/Index', [
            'report' => $report,
            'businessDate' => $businessDate->toDateString(),
            'hotelId' => $hotelId,
        ]);
    }

    public function pdf(Request $request)
    {
        $this->authorize('night_audit.export');

        $user = $request->user();
        $hotelId = (int) ($request->integer('hotel_id') ?: ($user->active_hotel_id ?? $user->hotel_id));

        abort_if($hotelId === 0, 404, 'Hôtel introuvable.');

        $businessDate = $this->businessDateFromRequest($request);

        $report = $this->nightAuditService->cachedGenerate(
            (int) $user->tenant_id,
            $hotelId,
            $businessDate,
            $request->boolean('refresh'),
        );

        return response()->view('night-audit.pdf', [
            'report' => $report,
        ]);
    }

    private function businessDateFromRequest(Request $request): Carbon
    {
        $date = $request->string('date')->toString();

        return $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();
    }
}
