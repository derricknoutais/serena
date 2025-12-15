<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $permissions = [
            // Operational
            'reservations.override_datetime',
            'folio_items.void',
            'housekeeping.mark_inspected',
            'housekeeping.mark_clean',
            'housekeeping.mark_dirty',
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

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => $guard],
            );
        }

        $roleMap = [
            'owner' => $permissions,
            'manager' => $permissions,
            'receptionist' => [
                'rooms.view',
                'room_types.view',
                'offers.view',
                'products.view',
                'product_categories.view',
                'taxes.view',
                'payment_methods.view',
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
                'rooms.view',
                'housekeeping.mark_clean',
                'housekeeping.mark_dirty',
                'maintenance_tickets.view',
                'maintenance_tickets.create',
            ],
            'maintenance' => $permissions,
        ];

        foreach ($roleMap as $roleName => $allowed) {
            /** @var Role $role */
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                continue;
            }

            $role->syncPermissions($allowed);
        }
    }
}
