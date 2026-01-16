<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\ActivityFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationTimelineController extends Controller
{
    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorize('frontdesk.view');

        /** @var User $user */
        $user = $request->user();

        if ($reservation->tenant_id !== $user->tenant_id) {
            abort(404);
        }

        if ((int) $reservation->hotel_id !== (int) ($user->active_hotel_id ?? $user->hotel_id)) {
            abort(404);
        }

        $reservation->loadMissing(['room', 'folios', 'folios.payments']);

        $folioIds = $reservation->folios->pluck('id')->all();
        $paymentIds = Payment::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $reservation->hotel_id)
            ->whereIn('folio_id', $folioIds)
            ->pluck('id')
            ->all();

        $roomId = $reservation->room_id;

        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->where('tenant_id', $user->tenant_id)
            ->where(function ($query) use ($reservation, $folioIds, $paymentIds, $roomId): void {
                $query
                    ->where(function ($inner) use ($reservation): void {
                        $inner->where('subject_type', Reservation::class)
                            ->where('subject_id', (string) $reservation->id);
                    })
                    ->orWhere(function ($inner) use ($folioIds): void {
                        if (! empty($folioIds)) {
                            $inner->where('subject_type', Folio::class)
                                ->whereIn('subject_id', array_map('strval', $folioIds));
                        }
                    })
                    ->orWhere(function ($inner) use ($paymentIds): void {
                        if (! empty($paymentIds)) {
                            $inner->where('subject_type', Payment::class)
                                ->whereIn('subject_id', array_map('strval', $paymentIds));
                        }
                    })
                    ->orWhere(function ($inner) use ($roomId): void {
                        if ($roomId) {
                            $inner->where('subject_type', \App\Models\Room::class)
                                ->where('subject_id', (string) $roomId);
                        }
                    });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (Activity $activity): array {
                $formatted = ActivityFormatter::format($activity);

                return [
                    'id' => $activity->id,
                    'happened_at' => $activity->created_at?->toDateTimeString(),
                    'module_label_fr' => $formatted['module_label_fr'],
                    'action_label_fr' => $formatted['action_label_fr'],
                    'sentence_fr' => $formatted['sentence_fr'],
                    'meta' => $formatted['meta'],
                    'causer' => $activity->causer
                        ? [
                            'id' => $activity->causer->getKey(),
                            'name' => $activity->causer->name,
                        ]
                        : null,
                ];
            });

        return response()->json([
            'timeline' => $activities,
        ]);
    }
}
