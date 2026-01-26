<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\LoyaltyPoint;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuestController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $search = $request->string('search')->toString();

        $guests = Guest::forTenant($tenantId)
            ->search($search)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Frontdesk/Guests/GuestsIndex', [
            'guests' => $guests,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Frontdesk/Guests/Create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'document_type' => ['nullable', 'string', 'max:50'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['tenant_id'] = $tenantId;

        /** @var Guest $guest */
        $guest = Guest::query()->create($data);

        if ($request->wantsJson()) {
            return response()->json([
                'guest' => [
                    'id' => $guest->id,
                    'first_name' => $guest->first_name,
                    'last_name' => $guest->last_name,
                    'email' => $guest->email,
                    'phone' => $guest->phone,
                    'full_name' => $guest->full_name ?? trim(($guest->last_name ?? '').' '.($guest->first_name ?? '')),
                    'name' => $guest->full_name ?? trim(($guest->last_name ?? '').' '.($guest->first_name ?? '')),
                    'balance_due' => 0,
                ],
            ]);
        }

        return redirect()
            ->route($this->guestIndexRoute($request))
            ->with('success', 'Client créé avec succès.');
    }

    public function show(Request $request, Guest $guest): Response
    {
        $tenantId = $request->user()->tenant_id;

        abort_unless($guest->tenant_id === $tenantId, 404);

        $reservations = Reservation::query()
            ->where('tenant_id', $tenantId)
            ->where('guest_id', $guest->id)
            ->with([
                'room:id,number',
                'roomType:id,name',
                'offer:id,name',
                'folios' => function ($query): void {
                    $query->where('is_main', true)
                        ->with([
                            'payments' => function ($query): void {
                                $query->whereNull('deleted_at')
                                    ->with('paymentMethod:id,name');
                            },
                        ]);
                },
            ])
            ->orderByDesc('check_in_date')
            ->get();

        $history = $reservations->map(function (Reservation $reservation): array {
            $folio = $reservation->folios->first();
            $payments = $folio?->payments?->map(function (Payment $payment): array {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at,
                    'method' => $payment->paymentMethod?->name,
                    'entry_type' => $payment->entry_type,
                ];
            })?->values() ?? collect();

            return [
                'id' => $reservation->id,
                'code' => $reservation->code,
                'status' => $reservation->status,
                'status_label' => $reservation->status_label,
                'check_in_date' => $reservation->check_in_date,
                'check_out_date' => $reservation->check_out_date,
                'room' => $reservation->room?->number,
                'room_type' => $reservation->roomType?->name,
                'offer' => $reservation->offer?->name ?? $reservation->offer_name,
                'total_amount' => $reservation->total_amount,
                'currency' => $reservation->currency,
                'folio_balance' => $folio?->balance,
                'payments' => $payments,
            ];
        });

        $loyaltyTotal = LoyaltyPoint::query()
            ->where('tenant_id', $tenantId)
            ->where('guest_id', $guest->id)
            ->sum('points');

        return Inertia::render('Frontdesk/Guests/Show', [
            'guest' => $guest->only([
                'id',
                'first_name',
                'last_name',
                'full_name',
                'email',
                'phone',
                'document_type',
                'document_number',
                'address',
                'city',
                'country',
                'notes',
            ]),
            'reservations' => $history,
            'loyalty' => [
                'total_points' => (int) $loyaltyTotal,
            ],
        ]);
    }

    public function edit(Request $request, Guest $guest): Response
    {
        $tenantId = $request->user()->tenant_id;

        abort_unless($guest->tenant_id === $tenantId, 404);

        return Inertia::render('Frontdesk/Guests/Edit', [
            'guest' => $guest->only([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'document_type',
                'document_number',
                'address',
                'city',
                'country',
                'notes',
            ]),
        ]);
    }

    public function update(Request $request, Guest $guest): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        abort_unless($guest->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'document_type' => ['nullable', 'string', 'max:50'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $guest->update($data);

        return redirect()
            ->route($this->guestIndexRoute($request))
            ->with('success', 'Client mis à jour.');
    }

    public function destroy(Request $request, Guest $guest): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        abort_unless($guest->tenant_id === $tenantId, 404);

        $guest->delete();

        return redirect()
            ->route($this->guestIndexRoute($request))
            ->with('success', 'Client supprimé.');
    }

    public function search(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $hotelId = $request->user()->active_hotel_id ?? $request->session()->get('active_hotel_id');
        $term = $request->string('search')->toString();

        $results = Guest::forTenant($tenantId)
            ->search($term)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(20)
            ->get([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
            ]);

        $balances = Folio::query()
            ->where('tenant_id', $tenantId)
            ->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))
            ->whereNotNull('guest_id')
            ->where('is_main', true)
            ->whereIn('guest_id', $results->pluck('id'))
            ->selectRaw('guest_id, SUM(balance) as balance')
            ->groupBy('guest_id')
            ->pluck('balance', 'guest_id');

        $payload = $results->map(function (Guest $guest) use ($balances): array {
            return [
                'id' => $guest->id,
                'first_name' => $guest->first_name,
                'last_name' => $guest->last_name,
                'email' => $guest->email,
                'phone' => $guest->phone,
                'full_name' => $guest->full_name ?? trim(($guest->last_name ?? '').' '.($guest->first_name ?? '')),
                'balance_due' => (float) ($balances[$guest->id] ?? 0),
            ];
        });

        return response()->json($payload);
    }

    public function summary(Request $request, Guest $guest): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        abort_unless($guest->tenant_id === $tenantId, 404);

        $reservationsBase = Reservation::query()
            ->where('tenant_id', $tenantId)
            ->where('guest_id', $guest->id);

        $reservations = (clone $reservationsBase)
            ->orderByDesc('check_in_date')
            ->get(['check_in_date', 'check_out_date']);

        $totalNights = $reservations->sum(function (Reservation $reservation): int {
            $checkIn = $reservation->check_in_date?->copy()?->startOfDay();
            $checkOut = $reservation->check_out_date?->copy()?->startOfDay();

            if (! $checkIn || ! $checkOut) {
                return 0;
            }

            return max(0, $checkIn->diffInDays($checkOut));
        });

        $lastStay = (clone $reservationsBase)
            ->orderByDesc('check_in_date')
            ->value('check_in_date');

        $balanceDue = Folio::query()
            ->where('tenant_id', $tenantId)
            ->where('guest_id', $guest->id)
            ->where('is_main', true)
            ->sum('balance');

        $totalSpent = Payment::query()
            ->whereNull('deleted_at')
            ->whereHas('folio', function ($query) use ($tenantId, $guest): void {
                $query->where('tenant_id', $tenantId)
                    ->where('guest_id', $guest->id);
            })
            ->sum('amount');

        $loyaltyTotal = LoyaltyPoint::query()
            ->where('tenant_id', $tenantId)
            ->where('guest_id', $guest->id)
            ->sum('points');

        $recentPoints = LoyaltyPoint::query()
            ->where('tenant_id', $tenantId)
            ->where('guest_id', $guest->id)
            ->with('reservation:id,code')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (LoyaltyPoint $point): array {
                return [
                    'id' => $point->id,
                    'points' => $point->points,
                    'type' => $point->type,
                    'reservation_code' => $point->reservation?->code,
                    'created_at' => $point->created_at,
                ];
            });

        return response()->json([
            'guest' => [
                'id' => $guest->id,
                'first_name' => $guest->first_name,
                'last_name' => $guest->last_name,
                'full_name' => $guest->full_name,
                'email' => $guest->email,
                'phone' => $guest->phone,
                'document_type' => $guest->document_type,
                'document_number' => $guest->document_number,
                'address' => $guest->address,
                'city' => $guest->city,
                'country' => $guest->country,
                'notes' => $guest->notes,
            ],
            'analytics' => [
                'reservations_total' => (clone $reservationsBase)->count(),
                'reservations_active' => (clone $reservationsBase)
                    ->whereIn('status', Reservation::activeStatusForAvailability())
                    ->count(),
                'reservations_completed' => (clone $reservationsBase)
                    ->where('status', Reservation::STATUS_CHECKED_OUT)
                    ->count(),
                'total_nights' => (int) $totalNights,
                'total_spent' => (float) $totalSpent,
                'balance_due' => (float) $balanceDue,
                'last_stay_at' => $lastStay,
            ],
            'loyalty' => [
                'total_points' => (int) $loyaltyTotal,
                'recent' => $recentPoints,
            ],
        ]);
    }

    private function guestIndexRoute(Request $request): string
    {
        return $request->is('settings/resources/*')
            ? 'ressources.guests.index'
            : 'guests.index';
    }
}
