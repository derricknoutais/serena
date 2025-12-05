<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\CashTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CashSessionController extends Controller
{
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

    public function store(Request $request): RedirectResponse
    {
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

        CashSession::create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $user->active_hotel_id,
            'opened_by_user_id' => $user->id,
            'type' => $data['type'],
            'started_at' => now(),
            'starting_amount' => $data['starting_amount'],
            'status' => 'open',
            'notes' => $data['notes'],
        ]);

        return back();
    }

    public function close(Request $request, CashSession $cashSession): RedirectResponse
    {
        if ($cashSession->status !== 'open') {
            abort(403, 'Session déjà fermée.');
        }

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
            'notes' => $data['notes'] ? $cashSession->notes . "\n[Fermeture] " . $data['notes'] : $cashSession->notes,
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