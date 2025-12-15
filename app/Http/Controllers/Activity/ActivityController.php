<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterActivityRequest;
use App\Models\Guest;
use App\Models\Offer;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    /**
     * @var array<int, string>
     */
    private array $offerNames = [];

    /**
     * @var array<string, string>
     */
    private array $roomNumbers = [];

    /**
     * @var array<int, string>
     */
    private array $guestNames = [];

    public function index(FilterActivityRequest $request): Response
    {
        $filters = $request->validated();

        $query = Activity::query()
            ->with(['causer'])
            ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($filters['user_id'] ?? null, fn ($q, $userId) => $q->where('causer_id', $userId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('description', 'like', '%'.$search.'%');
            })
            ->orderByDesc('created_at');

        $activities = $query
            ->paginate(20)
            ->withQueryString()
            ->through(function (Activity $activity) {
                $properties = $activity->properties?->toArray() ?? [];
                $readableProperties = $this->readableProperties($properties);

                if (isset($properties['tenant_id'])) {
                    $tenantName = Tenant::query()
                        ->find($properties['tenant_id'])
                        ?->name ?? $properties['tenant_id'];

                    $properties['tenant_id'] = $tenantName;
                    $properties['tenant'] = $tenantName;
                }

                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'created_at' => $activity->created_at?->toDateTimeString(),
                    'causer' => $activity->causer
                        ? [
                            'id' => $activity->causer->getKey(),
                            'name' => $activity->causer->name,
                            'email' => $activity->causer->email,
                        ]
                        : null,
                    'properties' => $properties,
                    'readable_properties' => $readableProperties,
                ];
            });

        $users = \App\Models\User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $events = Activity::query()->select('event')->whereNotNull('event')->distinct()->pluck('event')->all();

        return Inertia::render('activity/Index', [
            'activities' => $activities,
            'filters' => Arr::only($filters, ['event', 'user_id', 'search']),
            'users' => $users,
            'events' => $events,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return list<array{label: string, value: string}>
     */
    private function readableProperties(array $properties): array
    {
        if (isset($properties['offer_name'])) {
            unset($properties['offer_id']);
        }

        if (isset($properties['offer_id']) && ! isset($properties['offer_name'])) {
            $properties['offer_name'] = $this->resolveOfferName((int) $properties['offer_id']);
            if ($properties['offer_name']) {
                unset($properties['offer_id']);
            }
        }

        if (isset($properties['room_number'])) {
            unset($properties['room_id']);
        }

        if (isset($properties['room_id']) && ! isset($properties['room_number'])) {
            $properties['room_number'] = $this->resolveRoomNumber((string) $properties['room_id']);
            if ($properties['room_number']) {
                unset($properties['room_id']);
            }
        }

        if (isset($properties['guest_name'])) {
            unset($properties['guest_id']);
        }

        if (isset($properties['guest_id']) && ! isset($properties['guest_name'])) {
            $properties['guest_name'] = $this->resolveGuestName((int) $properties['guest_id']);
            if ($properties['guest_name']) {
                unset($properties['guest_id']);
            }
        }

        $labels = [
            'reservation_code' => 'Réservation',
            'room_number' => 'Chambre',
            'room_id' => 'Chambre',
            'offer_id' => 'Offre',
            'offer_name' => 'Offre',
            'from_status' => 'Statut avant',
            'to_status' => 'Statut après',
            'check_in_date' => 'Arrivée',
            'check_out_date' => 'Départ',
            'actual_check_in_at' => 'Check-in',
            'actual_check_out_at' => 'Check-out',
            'hotel_id' => 'Hôtel',
            'guest_name' => 'Client',
            'guest_id' => 'Client',
        ];

        $formatted = [];

        foreach ($properties as $key => $value) {
            $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
            $display = $this->stringifyPropertyValue($value);

            $formatted[] = [
                'label' => $label,
                'value' => $display,
            ];
        }

        return $formatted;
    }

    private function stringifyPropertyValue(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_scalar($value)) {
            return $this->formatDateString((string) $value);
        }

        if (is_array($value)) {
            return collect($value)->map(fn ($v, $k) => $k.': '.$this->stringifyPropertyValue($v))->implode(', ');
        }

        return (string) $value;
    }

    private function formatDateString(string $value): string
    {
        if (! str_contains($value, 'T')) {
            return $value;
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function resolveOfferName(int $id): ?string
    {
        if ($id === 0) {
            return null;
        }

        if (isset($this->offerNames[$id])) {
            return $this->offerNames[$id];
        }

        $this->offerNames[$id] = Offer::query()->whereKey($id)->value('name') ?? null;

        return $this->offerNames[$id];
    }

    private function resolveRoomNumber(string $id): ?string
    {
        if ($id === '') {
            return null;
        }

        if (isset($this->roomNumbers[$id])) {
            return $this->roomNumbers[$id];
        }

        $this->roomNumbers[$id] = Room::query()->whereKey($id)->value('number') ?? null;

        return $this->roomNumbers[$id];
    }

    private function resolveGuestName(int $id): ?string
    {
        if ($id === 0) {
            return null;
        }

        if (isset($this->guestNames[$id])) {
            return $this->guestNames[$id];
        }

        $guest = Guest::query()->find($id);
        $this->guestNames[$id] = $guest
            ? trim(($guest->first_name ?? '').' '.($guest->last_name ?? ''))
            : null;

        return $this->guestNames[$id];
    }
}
