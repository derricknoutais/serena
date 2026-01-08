<?php

namespace Database\Seeders;

use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class PushSubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->first();

        if (! $tenant) {
            return;
        }

        $user = User::query()->where('tenant_id', $tenant->id)->first();

        PushSubscription::factory()
            ->count(3)
            ->state([
                'tenant_id' => $tenant->id,
                'user_id' => $user?->id,
            ])
            ->create();
    }
}
