<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SaasDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1️⃣ Create the Tenant
        $tenant = Tenant::create([
            'id' => 'demo-tenant',
            'data' => [
                'name' => 'Demo Hotel',
                'contact_email' => 'info@demo-hotel.test',
                'plan' => 'standard',
                'hotel' => [
                    'currency' => 'XAF',
                    'timezone' => 'Africa/Douala',
                    'checkin_time' => '14:00',
                    'checkout_time' => '12:00',
                ],
                'settings' => [
                    'tax_rate' => 0.19,
                    'rooms_auto_numbering' => true,
                ],
            ],
        ]);

        // 2️⃣ Assign a domain to this tenant
        $tenant->createDomain([
            'domain' => 'demo.app.test',
        ]);

        // 3️⃣ Create a tenant user
        // Because User uses BelongsToTenant, tenant_id is auto-filled
        tenancy()->initialize($tenant); // <-- required for assigning tenant_id automatically

        User::create([
            'name' => 'Demo Admin',
            'email' => 'admin@demo.app.test',
            'password' => Hash::make('password'), // change in production
        ]);

        tenancy()->end(); // cleanup

        $this->command->info('Tenant and Demo User created successfully!');
    }
}
