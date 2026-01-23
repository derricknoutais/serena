<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationSettingsRequest;
use App\Models\NotificationPreference;
use App\Support\NotificationEventCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $tenantId = (string) $user->tenant_id;
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        $preferences = NotificationPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('hotel_id', $hotelId)
            ->get()
            ->keyBy('event_key');

        $events = collect(NotificationEventCatalog::all())
            ->map(function (array $event) use ($preferences): array {
                $preference = $preferences->get($event['key']);
                $roles = $preference?->roles ?? $event['roles'];
                $channels = $preference?->channels ?? $event['channels'];

                return [
                    'key' => $event['key'],
                    'label' => $event['label'],
                    'description' => $event['description'],
                    'roles' => $roles,
                    'channels' => $channels,
                    'default_roles' => $event['roles'],
                    'default_channels' => $event['channels'],
                ];
            })
            ->values();

        $roles = [
            ['name' => 'owner', 'label' => 'Owner'],
            ['name' => 'manager', 'label' => 'Manager'],
            ['name' => 'supervisor', 'label' => 'Superviseur'],
            ['name' => 'receptionist', 'label' => 'Réception'],
            ['name' => 'housekeeping', 'label' => 'Ménage'],
            ['name' => 'accountant', 'label' => 'Comptable'],
        ];

        $channels = [
            ['key' => NotificationEventCatalog::CHANNEL_IN_APP, 'label' => 'Centre de notifications'],
            ['key' => NotificationEventCatalog::CHANNEL_PUSH, 'label' => 'Notifications push (VAPID)'],
        ];

        return Inertia::render('settings/Notifications', [
            'hotel' => [
                'id' => $hotelId,
            ],
            'events' => $events,
            'roles' => $roles,
            'channels' => $channels,
        ]);
    }

    public function update(UpdateNotificationSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $tenantId = (string) $user->tenant_id;
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        $payload = $request->validated('events');

        foreach ($payload as $eventKey => $config) {
            NotificationPreference::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'hotel_id' => $hotelId,
                    'event_key' => $eventKey,
                ],
                [
                    'roles' => array_values($config['roles'] ?? []),
                    'channels' => array_values($config['channels'] ?? []),
                ],
            );
        }

        return back()->with('success', 'Paramètres de notifications mis à jour.');
    }
}
