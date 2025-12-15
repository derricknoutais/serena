<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 403);

        $tenantId = $user->tenant_id;
        $hotelId = $user->active_hotel_id ?? $request->session()->get('active_hotel_id');
        $latest = $request->boolean('latest', false);
        $limit = min(max((int) $request->integer('limit', 10), 1), 50);

        if (! Schema::hasColumn('notifications', 'tenant_id')) {
            return response()->json([
                'notifications' => [],
                'unread_count' => 0,
            ]);
        }

        $query = $user->notifications()
            ->where('tenant_id', $tenantId)
            ->when($hotelId, fn ($q) => $q->where(function ($sub) use ($hotelId): void {
                $sub->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
            }))
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest();

        if ($latest || $request->wantsJson()) {
            $notifications = $query->limit($limit)->get()->map(fn (DatabaseNotification $notification) => $this->transform($notification));

            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $this->unreadCount($user->id, $tenantId, $hotelId),
            ]);
        }

        $notifications = $query->paginate(20)->through(fn (DatabaseNotification $notification) => $this->transform($notification));

        return Inertia::render('Notifications/Index', [
            'filters' => [
                'unread' => $request->boolean('unread'),
            ],
            'notifications' => $notifications,
            'unread_count' => $this->unreadCount($user->id, $tenantId, $hotelId),
        ]);
    }

    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 403);

        $notification = $user->notifications()->where('id', $notificationId)->firstOrFail();
        $notification->forceFill(['read_at' => now()])->save();

        return response()->json([
            'status' => 'ok',
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 403);

        $tenantId = $user->tenant_id;
        $hotelId = $user->active_hotel_id ?? $request->session()->get('active_hotel_id');

        $query = $user->notifications()->where('tenant_id', $tenantId);

        if ($hotelId) {
            $query->where(function ($q) use ($hotelId): void {
                $q->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
            });
        }

        $query->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'status' => 'ok',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'event' => $notification->data['event'] ?? null,
            'title' => $notification->data['title'] ?? '',
            'message' => $notification->data['message'] ?? '',
            'cta_url' => $notification->data['cta_url'] ?? null,
            'cta_route' => $notification->data['cta_route'] ?? null,
            'payload' => $notification->data['payload'] ?? [],
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }

    private function unreadCount(int $userId, string $tenantId, ?int $hotelId = null): int
    {
        if (! Schema::hasTable('notifications')) {
            return 0;
        }

        $notifiableType = auth()->user() ? get_class(auth()->user()) : \App\Models\User::class;

        $query = DatabaseNotification::query()
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $userId)
            ->where('tenant_id', $tenantId)
            ->whereNull('read_at');

        if ($hotelId) {
            $query->where(function ($q) use ($hotelId): void {
                $q->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
            });
        }

        return (int) $query->count();
    }
}
