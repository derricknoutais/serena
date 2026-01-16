<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Hotel;
use App\Models\User;
use App\Support\ActivityFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ActivityJournalController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('journal.view');

        /** @var User $user */
        $user = $request->user();
        $filters = $this->filters($request);
        $canViewAllHotels = $user->hasRole(['owner', 'manager', 'superadmin']);

        $hotelId = $this->activeHotelId($request);
        if ($canViewAllHotels) {
            if ($filters['all_hotels'] ?? false) {
                $hotelId = null;
            } elseif (! empty($filters['hotel_id'])) {
                $hotelId = (int) $filters['hotel_id'];
            }
        }

        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->where('tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->when($filters['module'] ?? null, fn ($q, $module) => $q->where('log_name', $module))
            ->when($filters['action'] ?? null, function ($q, $action) {
                $q->where(function ($inner) use ($action): void {
                    $inner->where('description', $action)
                        ->orWhere('event', $action);
                });
            })
            ->when($filters['user_id'] ?? null, fn ($q, $userId) => $q->where('causer_id', $userId))
            ->when($filters['subject_type'] ?? null, fn ($q, $type) => $q->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn ($q, $id) => $q->where('subject_id', $id))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(function ($inner) use ($term): void {
                    $inner->where('description', 'like', '%'.$term.'%')
                        ->orWhere('log_name', 'like', '%'.$term.'%')
                        ->orWhere('subject_type', 'like', '%'.$term.'%')
                        ->orWhere('subject_id', 'like', '%'.$term.'%')
                        ->orWhere('properties', 'like', '%'.$term.'%');
                });
            })
            ->orderByDesc('created_at');

        $activities = $query
            ->paginate(50)
            ->withQueryString()
            ->through(function (Activity $activity) {
                $formatted = ActivityFormatter::format($activity);

                return [
                    'id' => $activity->id,
                    'happened_at' => $activity->created_at?->toDateTimeString(),
                    'module' => $activity->log_name,
                    'module_label_fr' => $formatted['module_label_fr'],
                    'action' => $activity->description ?? $activity->event,
                    'action_label_fr' => $formatted['action_label_fr'],
                    'causer' => $activity->causer
                        ? [
                            'id' => $activity->causer->getKey(),
                            'name' => $activity->causer->name,
                        ]
                        : null,
                    'subject' => [
                        'type' => $activity->subject_type,
                        'id' => $activity->subject_id,
                        'label' => $formatted['subject_label_fr'],
                    ],
                    'summary_fr' => $formatted['sentence_fr'],
                    'meta' => $formatted['meta'],
                    'properties' => $activity->properties?->toArray() ?? [],
                ];
            });

        $users = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $actions = Activity::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereNotNull('description')
            ->distinct()
            ->orderBy('description')
            ->limit(200)
            ->pluck('description')
            ->values();

        $hotels = $canViewAllHotels
            ? Hotel::query()
                ->where('tenant_id', $user->tenant_id)
                ->orderBy('name')
                ->get(['id', 'name'])
            : [];

        return Inertia::render('Journal/Index', [
            'activities' => $activities,
            'filters' => $filters,
            'users' => $users,
            'moduleOptions' => collect(ActivityFormatter::moduleMap())
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'actionOptions' => $actions,
            'canViewAllHotels' => $canViewAllHotels,
            'hotels' => $hotels,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:50'],
            'action' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'subject_type' => ['nullable', 'string', 'max:150'],
            'subject_id' => ['nullable', 'string', 'max:50'],
            'hotel_id' => ['nullable', 'integer'],
            'all_hotels' => ['nullable', 'boolean'],
        ]);

        return Arr::only($validated, [
            'q',
            'module',
            'action',
            'user_id',
            'date_from',
            'date_to',
            'subject_type',
            'subject_id',
            'hotel_id',
            'all_hotels',
        ]);
    }
}
