<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'isTenantDomain' => function_exists('tenant') && tenant() !== null,
            'auth' => [
                'user' => $user
                    ? $user->loadMissing(['roles', 'activeHotel', 'hotels:id,name'])
                    : null,
                'can' => [
                    ...$this->permissionFlags($request),
                ],
                'hotels' => $request->user()?->hotels()->select('hotels.id', 'hotels.name')->get() ?? collect(),
                'activeHotel' => $request->user()?->activeHotel,
                'hotelNotice' => $request->session()->get('hotel_notice'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'notifications' => [
                'unread_count' => $this->notificationCount($request),
            ],
            'webpush' => [
                'publicKey' => config('webpush.vapid.public_key'),
            ],
            'ui' => [
                'settings_resources_layout' => str_starts_with($request->path(), 'settings/resources'),
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function permissionFlags(Request $request): array
    {
        $user = $request->user();

        $permissions = [
            'frontdesk.view',
            'housekeeping.view',
            'analytics.view',
            'reservations.override_datetime',
            'reservations.extend_stay',
            'reservations.shorten_stay',
            'reservations.change_room',
            'payments.create',
            'payments.void',
            'payments.refund',
            'payments.override_closed_day',
            'payments.override_refund_limit',
            'folio_items.void',
            'housekeeping.mark_inspected',
            'housekeeping.mark_clean',
            'housekeeping.mark_dirty',
            'cash_sessions.view',
            'cash_sessions.open',
            'cash_sessions.close',
            'rooms.view', 'rooms.create', 'rooms.update', 'rooms.delete',
            'room_types.view', 'room_types.create', 'room_types.update', 'room_types.delete',
            'offers.view', 'offers.create', 'offers.update', 'offers.delete',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'product_categories.view', 'product_categories.create', 'product_categories.update', 'product_categories.delete',
            'taxes.view', 'taxes.create', 'taxes.update', 'taxes.delete',
            'payment_methods.view', 'payment_methods.create', 'payment_methods.update', 'payment_methods.delete',
            'resources.view',
            'maintenance_tickets.view', 'maintenance_tickets.create', 'maintenance_tickets.update', 'maintenance_tickets.close',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
            'pos.view', 'pos.create',
            'night_audit.view', 'night_audit.export',
        ];

        $flags = [];

        foreach ($permissions as $permission) {
            $key = str_replace('.', '_', $permission);
            $flags[$key] = $user?->can($permission) ?? false;
        }

        return $flags;
    }

    private function notificationCount(Request $request): int
    {
        $user = $request->user();

        if (! $user || ! Schema::hasTable('notifications')) {
            return 0;
        }

        $tenantId = $user->tenant_id;
        $hotelId = $user->active_hotel_id ?? $request->session()->get('active_hotel_id');

        if (! Schema::hasColumn('notifications', 'tenant_id')) {
            return 0;
        }

        $query = DatabaseNotification::query()
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
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
