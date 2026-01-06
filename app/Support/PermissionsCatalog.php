<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Permission;

class PermissionsCatalog
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            // Views
            'frontdesk.view',
            'housekeeping.view',
            'analytics.view',
            // Operational
            'reservations.override_datetime',
            'reservations.extend_stay',
            'reservations.shorten_stay',
            'reservations.change_room',
            'payments.create',
            'folio_items.void',
            'housekeeping.mark_inspected',
            'housekeeping.mark_clean',
            'housekeeping.mark_dirty',
            'cash_sessions.view',
            'cash_sessions.open',
            'cash_sessions.close',
            // Resources
            'rooms.view',
            'rooms.create',
            'rooms.update',
            'rooms.delete',
            'room_types.view',
            'room_types.create',
            'room_types.update',
            'room_types.delete',
            'offers.view',
            'offers.create',
            'offers.update',
            'offers.delete',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'product_categories.view',
            'product_categories.create',
            'product_categories.update',
            'product_categories.delete',
            'taxes.view',
            'taxes.create',
            'taxes.update',
            'taxes.delete',
            'payment_methods.view',
            'payment_methods.create',
            'payment_methods.update',
            'payment_methods.delete',
            // Maintenance
            'maintenance_tickets.view',
            'maintenance_tickets.create',
            'maintenance_tickets.update',
            'maintenance_tickets.close',
            // Invoices
            'invoices.view',
            'invoices.create',
            'invoices.update',
            'invoices.delete',
            // POS
            'pos.view',
            'pos.create',
            // Night Audit
            'night_audit.view',
            'night_audit.export',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function roleMap(): array
    {
        $allPermissions = self::all();

        return [
            'owner' => $allPermissions,
            'manager' => $allPermissions,
            'superadmin' => $allPermissions,
            'receptionist' => [
                'frontdesk.view',
                'rooms.view',
                'room_types.view',
                'offers.view',
                'products.view',
                'product_categories.view',
                'taxes.view',
                'payment_methods.view',
                'reservations.extend_stay',
                'reservations.shorten_stay',
                'reservations.change_room',
                'payments.create',
                'cash_sessions.view',
                'cash_sessions.open',
                'housekeeping.mark_clean',
                'housekeeping.mark_dirty',
                'maintenance_tickets.view',
                'maintenance_tickets.create',
                'maintenance_tickets.update',
                'invoices.view',
                'pos.view',
                'pos.create',
                'night_audit.view',
                'night_audit.export',
            ],
            'housekeeping' => [
                'housekeeping.view',
                'rooms.view',
                'housekeeping.mark_clean',
                'housekeeping.mark_dirty',
                'maintenance_tickets.view',
                'maintenance_tickets.create',
            ],
            'supervisor' => [
                'housekeeping.view',
                'rooms.view',
                'housekeeping.mark_inspected',
                'housekeeping.mark_clean',
                'housekeeping.mark_dirty',
                'maintenance_tickets.view',
                'maintenance_tickets.create',
            ],
            'maintenance' => $allPermissions,
        ];
    }

    public static function ensureExists(?string $guardName = null): void
    {
        $guard = $guardName ?? config('auth.defaults.guard', 'web');

        try {
            foreach (self::all() as $permission) {
                Permission::findOrCreate($permission, $guard);
            }
        } catch (QueryException) {
            return;
        }
    }
}
