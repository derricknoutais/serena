<?php

namespace App\Http\Controllers;

use App\Http\Requests\PushSubscribeRequest;
use App\Http\Requests\PushUnsubscribeRequest;
use App\Models\PushSubscription;
use App\Notifications\GenericPushNotification;
use App\Services\PushNotificationSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(PushSubscribeRequest $request): JsonResponse
    {
        $tenantId = tenant()?->getTenantKey();

        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant introuvable.',
            ], 404);
        }

        $user = $request->user();
        $data = $request->validated();
        $keys = $data['keys'];
        $contentEncoding = $data['contentEncoding'] ?? 'aesgcm';

        $subscription = PushSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('endpoint', $data['endpoint'])
            ->first();

        $payload = [
            'public_key' => $keys['p256dh'],
            'auth_token' => $keys['auth'],
            'content_encoding' => $contentEncoding === '' ? 'aesgcm' : $contentEncoding,
            'user_agent' => $data['userAgent'] ?? $request->userAgent(),
            'last_seen_at' => now(),
        ];

        if ($subscription) {
            $subscription->fill($payload);

            if ($user) {
                $subscription->user_id = $user->id;
            }

            $subscription->save();
        } else {
            $subscription = PushSubscription::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $user?->id,
                'endpoint' => $data['endpoint'],
                ...$payload,
            ]);
        }

        return response()->json([
            'subscribed' => true,
            'subscription_id' => $subscription->id,
        ]);
    }

    public function destroy(PushUnsubscribeRequest $request): JsonResponse
    {
        $tenantId = tenant()?->getTenantKey();

        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant introuvable.',
            ], 404);
        }

        $endpoint = $request->validated('endpoint');
        $user = $request->user();

        $query = PushSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('endpoint', $endpoint);

        if ($user) {
            $query->where(function ($inner) use ($user): void {
                $inner->whereNull('user_id')->orWhere('user_id', $user->id);
            });
        }

        $deleted = $query->delete();

        return response()->json([
            'deleted' => $deleted > 0,
        ]);
    }

    public function test(Request $request, PushNotificationSender $sender): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Non authentifié.',
            ], 401);
        }

        $tenantId = (string) $user->tenant_id;
        $hotelId = $user->active_hotel_id ?? $request->session()->get('active_hotel_id');

        $notification = new GenericPushNotification(
            title: 'Notification de test',
            body: 'Les notifications push sont bien activées.',
            url: '/dashboard',
            icon: '/icons/icon-192.png',
            badge: '/icons/badge-192.png',
            tag: sprintf('push-test-%s', $user->id),
            tenantId: $tenantId,
            hotelId: $hotelId,
        );

        $sender->send(
            tenantId: $tenantId,
            notification: $notification,
            userIds: [$user->id],
        );

        return response()->json([
            'sent' => true,
        ]);
    }
}
