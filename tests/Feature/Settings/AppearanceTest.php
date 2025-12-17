<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('appearance settings page can be rendered', function () {
    $this->withoutVite();
    $this->seed(PermissionSeeder::class);

    $slug = 'appearance-tenant';
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'name' => 'Appearance Tenant',
        'slug' => $slug,
        'contact_email' => 'appearance@example.test',
        'plan' => 'standard',
        'data' => ['name' => 'Appearance Tenant', 'slug' => $slug],
    ]);

    $domain = 'appearance.test';
    $tenant->createDomain(['domain' => $domain]);

    tenancy()->initialize($tenant);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($user)
        ->get("http://{$domain}/settings/appearance")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Appearance'));
});
