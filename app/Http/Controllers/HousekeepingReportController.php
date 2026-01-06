<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class HousekeepingReportController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user && $user->can('housekeeping.view'), 403);
        $tenantId = (string) $user->tenant_id;
        $hotelId = $this->resolveHotelId($user);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        $hotel = Hotel::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $hotelId)
            ->first();

        $timezone = $hotel?->timezone ?? config('app.timezone');
        [$from, $to, $preset, $fromInput, $toInput] = $this->resolveDateRange($request, $timezone);

        $cleaningTasksQuery = HousekeepingTask::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('type', HousekeepingTask::TYPE_CLEANING)
            ->where('status', HousekeepingTask::STATUS_DONE)
            ->whereBetween('ended_at', [$from, $to]);

        $inspectionTasksQuery = HousekeepingTask::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('type', HousekeepingTask::TYPE_INSPECTION)
            ->where('status', HousekeepingTask::STATUS_DONE)
            ->whereBetween('ended_at', [$from, $to]);

        $roomsCleaned = (clone $cleaningTasksQuery)->distinct('room_id')->count('room_id');
        $roomsInspected = (clone $inspectionTasksQuery)
            ->where('outcome', HousekeepingTask::OUTCOME_PASSED)
            ->distinct('room_id')
            ->count('room_id');
        $roomsRedone = (clone $inspectionTasksQuery)
            ->where('outcome', HousekeepingTask::OUTCOME_FAILED)
            ->distinct('room_id')
            ->count('room_id');

        $avgCleaningSeconds = (clone $cleaningTasksQuery)->avg('duration_seconds');
        $avgCleaningSeconds = $avgCleaningSeconds !== null
            ? (int) round((float) $avgCleaningSeconds)
            : 0;

        $rooms = Room::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->orderBy('floor')
            ->orderBy('number')
            ->get(['id', 'number', 'floor', 'hk_status']);

        $roomIds = $rooms->pluck('id')->all();

        $lastCleaningTasks = HousekeepingTask::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereIn('room_id', $roomIds)
            ->where('type', HousekeepingTask::TYPE_CLEANING)
            ->where('status', HousekeepingTask::STATUS_DONE)
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->get()
            ->groupBy('room_id')
            ->map(fn ($tasks) => $tasks->first());

        $lastInspectionTasks = HousekeepingTask::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereIn('room_id', $roomIds)
            ->where('type', HousekeepingTask::TYPE_INSPECTION)
            ->where('status', HousekeepingTask::STATUS_DONE)
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->get()
            ->groupBy('room_id')
            ->map(fn ($tasks) => $tasks->first());

        $redoSince = Carbon::now($timezone)->subDays(30)->startOfDay();
        $redoCounts = HousekeepingTask::query()
            ->select('room_id')
            ->selectRaw('count(*) as redos')
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereIn('room_id', $roomIds)
            ->where('type', HousekeepingTask::TYPE_INSPECTION)
            ->where('outcome', HousekeepingTask::OUTCOME_FAILED)
            ->whereBetween('ended_at', [$redoSince, Carbon::now($timezone)])
            ->groupBy('room_id')
            ->pluck('redos', 'room_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        $roomsSummary = $rooms->map(function (Room $room) use ($lastCleaningTasks, $lastInspectionTasks, $redoCounts, $timezone) {
            $lastCleaning = $lastCleaningTasks->get($room->id);
            $lastInspection = $lastInspectionTasks->get($room->id);

            return [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'hk_status' => $room->hk_status,
                'last_cleaning_at' => $this->formatDateTime($lastCleaning?->ended_at, $timezone),
                'last_inspection_at' => $this->formatDateTime($lastInspection?->ended_at, $timezone),
                'last_inspection_outcome' => $lastInspection?->outcome,
                'redos_last_30_days' => $redoCounts[$room->id] ?? 0,
            ];
        })->values();

        $taskHistory = HousekeepingTask::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['room:id,number,floor', 'participants:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (HousekeepingTask $task) use ($timezone): array {
                return [
                    'id' => $task->id,
                    'type' => $task->type,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'created_at' => $this->formatDateTime($task->created_at, $timezone),
                    'started_at' => $this->formatDateTime($task->started_at, $timezone),
                    'ended_at' => $this->formatDateTime($task->ended_at, $timezone),
                    'duration_seconds' => $task->duration_seconds,
                    'outcome' => $task->outcome,
                    'room' => $task->room ? [
                        'id' => $task->room->id,
                        'number' => $task->room->number,
                        'floor' => $task->room->floor,
                    ] : null,
                    'participants' => $task->participants
                        ->map(fn ($participant): array => [
                            'id' => $participant->id,
                            'name' => $participant->name,
                        ])
                        ->values()
                        ->all(),
                ];
            });

        $completedTasks = HousekeepingTask::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->where('status', HousekeepingTask::STATUS_DONE)
            ->whereBetween('ended_at', [$from, $to])
            ->with('participants:id,name')
            ->get();

        $staffStats = [];
        foreach ($completedTasks as $task) {
            foreach ($task->participants as $participant) {
                $entry = $staffStats[$participant->id] ?? [
                    'id' => $participant->id,
                    'name' => $participant->name,
                    'tasks_participated' => 0,
                    'cleaning_seconds' => 0,
                    'inspections_performed' => 0,
                ];

                $entry['tasks_participated'] += 1;

                if ($task->type === HousekeepingTask::TYPE_CLEANING) {
                    $entry['cleaning_seconds'] += (int) ($task->duration_seconds ?? 0);
                }

                if ($task->type === HousekeepingTask::TYPE_INSPECTION) {
                    $entry['inspections_performed'] += 1;
                }

                $staffStats[$participant->id] = $entry;
            }
        }

        $statusHistory = Activity::query()
            ->where('event', 'hk_updated')
            ->where('subject_type', Room::class)
            ->whereIn('subject_id', $roomIds)
            ->whereBetween('created_at', [$from, $to])
            ->with('causer:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Activity $activity) use ($timezone): array {
                $properties = $activity->properties?->toArray() ?? [];

                return [
                    'id' => $activity->id,
                    'room_number' => $properties['room_number'] ?? null,
                    'from' => $properties['from_hk_status'] ?? null,
                    'to' => $properties['to_hk_status'] ?? null,
                    'remarks' => $properties['remarks'] ?? null,
                    'user' => $activity->causer?->name,
                    'created_at' => $this->formatDateTime($activity->created_at, $timezone),
                ];
            });

        return Inertia::render('Housekeeping/Reports', [
            'filters' => [
                'preset' => $preset,
                'from' => $fromInput,
                'to' => $toInput,
                'timezone' => $timezone,
            ],
            'summary' => [
                'rooms_cleaned' => $roomsCleaned,
                'rooms_inspected' => $roomsInspected,
                'rooms_redone' => $roomsRedone,
                'avg_cleaning_seconds' => $avgCleaningSeconds,
            ],
            'rooms' => $roomsSummary,
            'staff' => array_values($staffStats),
            'tasks' => $taskHistory,
            'statusHistory' => $statusHistory,
        ]);
    }

    /**
     * @return array{0:Carbon,1:Carbon,2:string,3:string,4:string}
     */
    private function resolveDateRange(Request $request, string $timezone): array
    {
        $preset = (string) $request->query('preset', 'today');
        $fromInput = (string) $request->query('from', '');
        $toInput = (string) $request->query('to', '');

        $now = Carbon::now($timezone);

        if ($preset === 'yesterday') {
            $from = $now->copy()->subDay()->startOfDay();
            $to = $from->copy()->endOfDay();

            return [$from, $to, $preset, $from->toDateString(), $to->toDateString()];
        }

        if ($preset === 'custom' && $fromInput !== '' && $toInput !== '') {
            $from = Carbon::parse($fromInput, $timezone)->startOfDay();
            $to = Carbon::parse($toInput, $timezone)->endOfDay();

            return [$from, $to, $preset, $from->toDateString(), $to->toDateString()];
        }

        $from = $now->copy()->startOfDay();
        $to = $now->copy()->endOfDay();

        return [$from, $to, 'today', $from->toDateString(), $to->toDateString()];
    }

    private function resolveHotelId(User $user): int
    {
        return (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);
    }

    private function formatDateTime(?Carbon $date, string $timezone): ?string
    {
        if (! $date) {
            return null;
        }

        return $date->copy()->setTimezone($timezone)->format('d/m/Y H:i');
    }
}
