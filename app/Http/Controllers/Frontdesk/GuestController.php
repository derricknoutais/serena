<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\Guest;
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
            ->route('guests.index')
            ->with('success', 'Client créé avec succès.');
    }

    public function show(Request $request, Guest $guest): Response
    {
        $tenantId = $request->user()->tenant_id;

        abort_unless($guest->tenant_id === $tenantId, 404);

        return Inertia::render('Frontdesk/Guests/Show', [
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
            // TODO: ajouter historique quand Reservation existera.
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
            ->route('guests.index')
            ->with('success', 'Client mis à jour.');
    }

    public function destroy(Request $request, Guest $guest): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        abort_unless($guest->tenant_id === $tenantId, 404);

        $guest->delete();

        return redirect()
            ->route('guests.index')
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
}
