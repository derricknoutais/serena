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
            'dashboard.view',
            // Operational
            'reservations.confirm',
            'reservations.check_in',
            'reservations.check_out',
            'reservations.check_out_with_balance',
            'reservations.override_datetime',
            'reservations.extend_stay',
            'reservations.shorten_stay',
            'reservations.change_room',
            'reservations.move_room',
            'reservations.view_details',
            'reservations.cancel',
            'reservations.delete',
            'reservations.force_status',
            'reservations.edit_prices',
            'reservations.change_guest',
            'reservations.change_offer',
            'reservations.override_stay_times',
            'folios.add_item',
            'folios.adjust',
            'payments.create',
            'payments.edit',
            'payments.delete',
            'payments.void',
            'payments.refund',
            'payments.override_closed_day',
            'payments.override_refund_limit',
            'folio_items.void',
            'folio_items.edit',
            'folio_items.delete',
            'housekeeping.mark_inspected',
            'housekeeping.mark_clean',
            'housekeeping.mark_dirty',
            'cash_sessions.view',
            'cash_sessions.open',
            'cash_sessions.close',
            'pos.tables.manage',
            'journal.view',
            // Resources
            'rooms.view',
            'rooms.create',
            'rooms.update',
            'rooms.delete',
            'room_types.view',
            'room_types.create',
            'room_types.update',
            'room_types.delete',
            'guests.view',
            'guests.create',
            'guests.update',
            'guests.delete',
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
            'stock.locations.manage',
            'stock.items.manage',
            'stock.purchases.create',
            'stock.purchases.update',
            'stock.purchases.receive',
            'stock.transfers.create',
            'stock.transfers.complete',
            'stock.inventories.create',
            'stock.inventories.post',
            'stock.override_negative',
            'hotels.documents.update',
            'resources.view',
            // Maintenance
            'maintenance_tickets.view',
            'maintenance_tickets.create',
            'maintenance_tickets.update',
            'maintenance_tickets.close',
            'maintenance.types.manage',
            'maintenance.technicians.manage',
            'maintenance.tickets.create',
            'maintenance.tickets.update',
            'maintenance.tickets.close',
            'maintenance.interventions.create',
            'maintenance.interventions.update',
            'maintenance.interventions.submit',
            'maintenance.interventions.approve',
            'maintenance.interventions.reject',
            'maintenance.interventions.mark_paid',
            'maintenance.interventions.add_stock_items',
            'maintenance.interventions.costs.manage',
            'maintenance.costs.view',
            'maintenance.costs.edit',
            'maintenance.costs.override_unit_cost',
            // Invoices
            'invoices.view',
            'invoices.create',
            'invoices.generate',
            'invoices.update',
            'invoices.delete',
            // POS
            'pos.view',
            'pos.create',
            'pos.stock.consume',
            'pos.stock.return',
            'stock.manage_bar_settings',
            'loyalty.settings.manage',
            // Night Audit
            'night_audit.view',
            'night_audit.export',
            'night_audit.close',
            'night_audit.reopen',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function roleMap(): array
    {
        $allPermissions = self::all();
        $overridePermissions = [
            'payments.override_closed_day',
            'payments.override_refund_limit',
            'hotels.documents.update',
            'pos.stock.return',
            'stock.manage_bar_settings',
            'loyalty.settings.manage',
        ];
        $defaultPermissions = array_values(array_diff($allPermissions, $overridePermissions));

        $managerOverrides = [
            'hotels.documents.update',
            'pos.stock.return',
            'stock.manage_bar_settings',
            'loyalty.settings.manage',
        ];

        return [
            'owner' => array_values(array_merge($defaultPermissions, $managerOverrides)),
            'manager' => array_values(array_merge($defaultPermissions, $managerOverrides)),
            'superadmin' => array_values(array_merge($defaultPermissions, $managerOverrides)),
            'receptionist' => [
                'dashboard.view',
                'frontdesk.view',
                'rooms.view',
                'room_types.view',
                'offers.view',
                'products.view',
                'product_categories.view',
                'taxes.view',
                'payment_methods.view',
                'reservations.confirm',
                'reservations.check_in',
                'reservations.check_out',
                'reservations.extend_stay',
                'reservations.shorten_stay',
                'reservations.change_room',
                'reservations.move_room',
                'reservations.view_details',
                'payments.create',
                'cash_sessions.view',
                'cash_sessions.open',
                'housekeeping.mark_clean',
                'housekeeping.mark_dirty',
                'maintenance_tickets.view',
                'maintenance_tickets.create',
                'maintenance_tickets.update',
                'maintenance.tickets.create',
                'maintenance.tickets.update',
                'maintenance.interventions.create',
                'maintenance.interventions.update',
                'maintenance.interventions.submit',
                'maintenance.interventions.costs.manage',
                'maintenance.interventions.add_stock_items',
                'maintenance.costs.edit',
                'stock.purchases.create',
                'stock.purchases.update',
                'stock.purchases.receive',
                'stock.transfers.create',
                'invoices.view',
                'invoices.generate',
                'pos.view',
                'pos.create',
                'night_audit.view',
                'night_audit.export',
                'folio_items.edit',
                'folio_items.delete',
                'folios.add_item',
                'guests.view',
                'guests.create',
                'guests.update',
            ],
            'housekeeping' => [
                'dashboard.view',
                'housekeeping.view',
                'rooms.view',
                'housekeeping.mark_clean',
                'housekeeping.mark_dirty',
                'maintenance_tickets.view',
                'maintenance_tickets.create',
                'maintenance.tickets.create',
            ],
            'supervisor' => [
                'dashboard.view',
                'housekeeping.view',
                'rooms.view',
                'housekeeping.mark_inspected',
                'housekeeping.mark_clean',
                'housekeeping.mark_dirty',
                'maintenance_tickets.view',
                'maintenance_tickets.create',
                'maintenance.tickets.create',
            ],
            'maintenance' => $defaultPermissions,
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
