<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterActivityRequest;
use App\Models\Tenant;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
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
}
