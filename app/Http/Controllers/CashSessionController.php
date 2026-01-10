<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Hotel;
use App\Services\NightAuditLockService;
use App\Services\Notifier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CashSessionController extends Controller
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly NightAuditLockService $lockService,
    ) {}

    /**
     * Manager view: List all sessions
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Only managers/owners should utilize this index view effectively
        // Or staffs seeing their own history.
        // For V2, let's make it the Manager Overview.

        return Inertia::render('CashSessions/CashSessionsIndex', [
            'sessions' => CashSession::query()
                ->with(['openedBy', 'closedBy'])
                ->where('tenant_id', $user->tenant_id)
                ->where('hotel_id', $user->active_hotel_id)
                ->orderByDesc('created_at')
                ->paginate(20)
                ->through(function ($session) {
                    $session->total_received = $session->calculateTotalReceived();

                    return $session;
                }),
        ]);
    }

    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type');

        $session = CashSession::query()
            ->with(['openedBy'])
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $user->active_hotel_id)
            ->where('type', $type)
            ->open()
            ->latest()
            ->first();

        if ($session) {
            $session->theoretical_balance = $session->calculateTheoreticalBalance();
            $session->total_received = $session->calculateTotalReceived();
        }

        return response()->json([
            'session' => $session,
        ]);
    }

    public function show(Request $request, CashSession $cashSession): Response
    {
        $user = $request->user();

        abort_unless($cashSession->tenant_id === $user->tenant_id, 404);
        abort_unless($cashSession->hotel_id === $user->active_hotel_id, 404);

        $cashSession->load([
            'openedBy:id,name',
            'closedBy:id,name',
            'validatedBy:id,name',
            'hotel:id,name',
            'transactions' => fn ($query) => $query->orderBy('created_at'),
            'payments' => fn ($query) => $query
                ->with([
                    'paymentMethod:id,name',
                    'createdBy:id,name',
                    'folio:id,code,reservation_id,guest_id',
                    'folio.reservation:id,code,guest_id',
                    'folio.guest:id,first_name,last_name',
                ])
                ->orderBy('paid_at'),
        ]);

        $session = [
            'id' => $cashSession->id,
            'type' => $cashSession->type,
            'status' => $cashSession->status,
            'currency' => $cashSession->currency,
            'starting_amount' => (float) $cashSession->starting_amount,
            'closing_amount' => $cashSession->closing_amount !== null ? (float) $cashSession->closing_amount : null,
            'expected_closing_amount' => $cashSession->expected_closing_amount !== null
                ? (float) $cashSession->expected_closing_amount
                : null,
            'difference_amount' => $cashSession->difference_amount !== null ? (float) $cashSession->difference_amount : null,
            'total_received' => $cashSession->calculateTotalReceived(),
            'theoretical_balance' => $cashSession->calculateTheoreticalBalance(),
            'started_at' => optional($cashSession->started_at)?->toDateTimeString(),
            'ended_at' => optional($cashSession->ended_at)?->toDateTimeString(),
            'validated_at' => optional($cashSession->validated_at)?->toDateTimeString(),
            'notes' => $cashSession->notes,
            'opened_by' => $cashSession->openedBy?->only(['id', 'name']),
            'closed_by' => $cashSession->closedBy?->only(['id', 'name']),
            'validated_by' => $cashSession->validatedBy?->only(['id', 'name']),
            'hotel' => $cashSession->hotel?->only(['id', 'name']),
        ];

        $transactions = $cashSession->transactions->map(static function ($transaction): array {
            return [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'description' => $transaction->description,
                'created_at' => optional($transaction->created_at)?->toDateTimeString(),
            ];
        });

        $payments = $cashSession->payments->map(static function ($payment): array {
            $reservation = $payment->folio?->reservation;
            $guest = $payment->folio?->guest;
            $guestName = $guest
                ? trim(($guest->last_name ?? '').' '.($guest->first_name ?? ''))
                : null;

            return [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => optional($payment->paid_at)?->toDateTimeString(),
                'method' => $payment->paymentMethod?->name,
                'created_by' => $payment->createdBy?->only(['id', 'name']),
                'folio_code' => $payment->folio?->code,
                'reservation_code' => $reservation?->code,
                'guest_name' => $guestName,
                'notes' => $payment->notes,
            ];
        });

        $paymentBreakdown = $cashSession->payments
            ->groupBy(fn ($payment) => $payment->paymentMethod?->name ?? 'Autre')
            ->map(fn ($group) => (float) $group->sum('amount'))
            ->sortKeys()
            ->map(fn ($amount, $method) => [
                'method' => $method,
                'amount' => $amount,
            ])
            ->values();

        return Inertia::render('CashSessions/CashSessionShow', [
            'session' => $session,
            'transactions' => $transactions,
            'payments' => $payments,
            'paymentBreakdown' => $paymentBreakdown,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('cash_sessions.open');

        $data = $request->validate([
            'starting_amount' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'in:frontdesk,bar'],
            'notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // Check uniqueness: Only one open session per type per hotel
        $exists = CashSession::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $user->active_hotel_id)
            ->where('type', $data['type'])
            ->open()
            ->exists();

        if ($exists) {
            return back()->withErrors(['session' => "Une caisse '{$data['type']}' est déjà ouverte pour cet hôtel."]);
        }

        $session = CashSession::create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $user->active_hotel_id,
            'opened_by_user_id' => $user->id,
            'type' => $data['type'],
            'started_at' => now(),
            'starting_amount' => $data['starting_amount'],
            'status' => 'open',
            'notes' => $data['notes'],
        ]);

        $this->notifier->notify('cash_session.opened', $session->hotel_id, [
            'tenant_id' => $session->tenant_id,
            'cash_session_id' => $session->id,
            'session_code' => sprintf('%s #%d', $session->type, $session->id),
            'user_name' => $user->name,
        ], [
            'cta_route' => 'cash.index',
        ]);

        return back();
    }

    public function close(Request $request, CashSession $cashSession): RedirectResponse
    {
        Gate::authorize('cash_sessions.close');

        if ($cashSession->status !== 'open') {
            abort(403, 'Session déjà fermée.');
        }

        $hotel = $cashSession->hotel ?? Hotel::query()->findOrFail($cashSession->hotel_id);
        $businessDate = Carbon::parse($cashSession->business_date ?? $cashSession->started_at);
        $this->lockService->assertBusinessDateOpen($hotel, $businessDate, $request->user(), $request->boolean('override_business_day'));

        $data = $request->validate([
            'closing_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $expected = $cashSession->calculateTheoreticalBalance();
        $actual = (float) $data['closing_amount'];
        $difference = $actual - $expected;

        $cashSession->update([
            'ended_at' => now(),
            'closed_by_user_id' => $request->user()->id,
            'closing_amount' => $actual,
            'expected_closing_amount' => $expected,
            'difference_amount' => $difference,
            'status' => 'closed_pending_validation',
            'notes' => $data['notes'] ? $cashSession->notes."\n[Fermeture] ".$data['notes'] : $cashSession->notes,
        ]);

        $this->notifier->notify('cash_session.closed', $cashSession->hotel_id, [
            'tenant_id' => $cashSession->tenant_id,
            'cash_session_id' => $cashSession->id,
            'session_code' => sprintf('%s #%d', $cashSession->type, $cashSession->id),
            'difference' => $difference,
        ], [
            'cta_route' => 'cash.index',
        ]);

        return back()->with('success', 'Caisse fermée. En attente de validation.');
    }

    public function validateSession(Request $request, CashSession $cashSession): RedirectResponse
    {
        // Add Gate/Policy check for 'manager' role here ideally

        if ($cashSession->status !== 'closed_pending_validation') {
            abort(403, 'Statut invalide pour validation.');
        }

        $cashSession->update([
            'validated_at' => now(),
            'validated_by_user_id' => $request->user()->id,
            'status' => 'validated',
        ]);

        return back()->with('success', 'Session de caisse validée.');
    }

    public function transaction(Request $request, CashSession $cashSession): RedirectResponse
    {
        if ($cashSession->status !== 'open') {
            abort(403, 'Caisse fermée.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'type' => ['required', 'in:deposit,withdrawal'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $amount = abs($data['amount']);
        if ($data['type'] === 'withdrawal') {
            $amount = -$amount;
        }

        $cashSession->transactions()->create([
            'amount' => $amount,
            'type' => $data['type'],
            'description' => $data['description'],
        ]);

        return back();
    }
}
