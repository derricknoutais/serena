<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ReservationLedgerController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('reservations.view_ledger');

        /** @var User $user */
        $user = $request->user();
        $hotelId = $this->activeHotelId($request);

        if ($hotelId === null) {
            abort(404, 'Aucun hôtel actif.');
        }

        $query = Reservation::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->with([
                'guest',
                'room',
                'roomType',
                'offer',
                'bookedBy',
                'mainFolio',
            ]);

        if ($codes = $request->input('code')) {
            $query->where('code', 'like', '%'.trim($codes).'%');
        }

        if ($guestSearch = $request->input('guest')) {
            $term = trim($guestSearch);
            $query->whereHas('guest', function ($builder) use ($term): void {
                $builder->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        if ($roomId = $request->input('room_id')) {
            $query->where('room_id', (int) $roomId);
        }

        if ($statuses = $request->input('status')) {
            $query->whereIn('status', Arr::wrap($statuses));
        }

        if ($from = $request->input('check_in_from')) {
            $query->whereDate('check_in_date', '>=', $from);
        }

        if ($until = $request->input('check_in_to')) {
            $query->whereDate('check_in_date', '<=', $until);
        }

        $allowedSorts = ['created_at', 'check_in_date', 'status', 'total_amount'];
        $sort = $request->input('sort', '-created_at');
        $direction = 'desc';
        if (! str_starts_with($sort, '-')) {
            $direction = 'asc';
        }
        $field = ltrim($sort, '-');

        if (! in_array($field, $allowedSorts, true)) {
            $field = 'created_at';
        }

        $query->orderBy($field, $direction);

        $reservations = $query->paginate((int) $request->input('per_page', 20))->appends($request->query());

        $payload = $reservations->through(function (Reservation $reservation) {
            return [
                'id' => $reservation->id,
                'code' => $reservation->code,
                'status' => $reservation->status,
                'status_label' => $reservation->status_label,
                'guest' => $reservation->guest?->only(['id', 'full_name', 'email', 'phone']),
                'room' => $reservation->room?->only(['id', 'number']),
                'room_type' => $reservation->roomType?->only(['id', 'name']),
                'offer' => $reservation->offer?->only(['id', 'name']),
                'check_in_date' => $reservation->check_in_date?->toDateString(),
                'check_out_date' => $reservation->check_out_date?->toDateString(),
                'total_amount' => (float) $reservation->total_amount,
                'currency' => $reservation->currency,
                'folio_balance' => (float) ($reservation->mainFolio?->balance ?? 0),
                'booked_by' => $reservation->bookedBy?->only(['id', 'name']),
                'created_at' => $reservation->created_at?->toDateTimeString(),
            ];
        });

        $facetRooms = Room::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->orderBy('number')
            ->get(['id', 'number']);

        return Inertia::render('Frontdesk/Reservations/LedgerIndex', [
            'reservations' => $payload,
            'filters' => [
                'code' => $request->input('code'),
                'guest' => $request->input('guest'),
                'room_id' => $request->input('room_id'),
                'status' => $request->input('status'),
                'check_in_from' => $request->input('check_in_from'),
                'check_in_to' => $request->input('check_in_to'),
                'sort' => $sort,
            ],
            'status_options' => Reservation::statusLabels(),
            'rooms' => $facetRooms,
            'can' => [
                'view_details' => $user->can('reservations.view_details'),
            ],
        ]);
    }
}
