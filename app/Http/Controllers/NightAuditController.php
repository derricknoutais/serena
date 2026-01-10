<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Services\NightAuditLockService;
use App\Services\NightAuditService;
use App\Services\Notifier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NightAuditController extends Controller
{
    public function __construct(
        private readonly NightAuditService $nightAuditService,
        private readonly Notifier $notifier,
        private readonly NightAuditLockService $lockService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('night_audit.view');

        $user = $request->user();
        $hotel = $this->resolveHotel($request);
        $businessDate = $this->businessDateFromRequest($request);

        $report = $this->nightAuditService->cachedGenerate(
            (int) $user->tenant_id,
            $hotel->id,
            $businessDate,
            $request->boolean('refresh'),
        );

        if ($request->boolean('refresh')) {
            $this->notifier->notify('business_day.closed', $hotel->id, [
                'tenant_id' => $user->tenant_id,
                'business_date' => $businessDate->toDateString(),
            ], [
                'cta_route' => 'night-audit.index',
                'cta_params' => ['date' => $businessDate->toDateString()],
            ]);
        }

        $closure = $this->lockService->closureFor($hotel, $businessDate);

        return Inertia::render('NightAudit/Index', [
            'report' => $report,
            'businessDate' => $businessDate->toDateString(),
            'hotelId' => $hotel->id,
            'window' => [
                'start' => $report['window']['start'],
                'end' => $report['window']['end'],
            ],
            'closure' => $closure ? [
                'status' => $closure->status,
                'closed_at' => optional($closure->closed_at)?->toDateTimeString(),
                'closed_by' => $closure->closedBy?->only(['id', 'name']),
                'summary' => $closure->summary ?? [],
            ] : null,
            'canClose' => $request->user()->can('night_audit.close'),
            'canReopen' => $request->user()->can('night_audit.reopen'),
            'closeRoute' => route('night-audit.close', ['business_date' => $businessDate->toDateString()]),
            'reopenRoute' => route('night-audit.reopen', ['business_date' => $businessDate->toDateString()]),
        ]);
    }

    public function pdf(Request $request)
    {
        $this->authorize('night_audit.export');

        $user = $request->user();
        $hotel = $this->resolveHotel($request);

        $businessDate = $this->businessDateFromRequest($request);

        $report = $this->nightAuditService->cachedGenerate(
            (int) $user->tenant_id,
            $hotel->id,
            $businessDate,
            $request->boolean('refresh'),
        );

        return response()->view('night-audit.pdf', [
            'report' => $report,
        ]);
    }

    public function close(Request $request, string $businessDate): RedirectResponse
    {
        $this->authorize('night_audit.close');

        $hotel = $this->resolveHotel($request);
        $resolvedDate = Carbon::parse($businessDate)->startOfDay();

        $report = $this->nightAuditService->generate(
            (int) $request->user()->tenant_id,
            $hotel->id,
            $resolvedDate,
        );

        $summary = [
            'occupancy' => $report['occupancy'],
            'revenue' => $report['revenue'],
            'payments_by_method' => $report['payments_by_method'],
            'total_payments' => $report['total_payments'],
            'cash_reconciliation' => $report['cash_reconciliation']['totals'],
            'window' => $report['window'],
        ];

        $this->lockService->closeDay($hotel, $resolvedDate, $summary, $request->user());

        return redirect()->route('night-audit.index', [
            'date' => $resolvedDate->toDateString(),
            'hotel_id' => $hotel->id,
        ])->with('success', 'La journée d’affaires a été clôturée.');
    }

    public function reopen(Request $request, string $businessDate): RedirectResponse
    {
        $this->authorize('night_audit.reopen');

        $hotel = $this->resolveHotel($request);
        $resolvedDate = Carbon::parse($businessDate)->startOfDay();

        $this->lockService->reopenDay($hotel, $resolvedDate, $request->user());

        return redirect()->route('night-audit.index', [
            'date' => $resolvedDate->toDateString(),
            'hotel_id' => $hotel->id,
        ])->with('success', 'La journée d’affaires est maintenant ouverte.');
    }

    private function resolveHotel(Request $request): Hotel
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $hotelId = (int) ($request->integer('hotel_id') ?: ($user->active_hotel_id ?? $user->hotel_id));

        abort_if($hotelId === 0, 404, 'Hôtel introuvable.');

        return Hotel::query()
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail($hotelId);
    }

    private function businessDateFromRequest(Request $request): Carbon
    {
        $date = $request->string('date')->toString();

        return $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();
    }
}
