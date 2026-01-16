<?php

require_once __DIR__.'/FolioTestHelpers.php';

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

it('renders the dashboard for receptionists', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('dashboard-receptionist');

    $user->assignRole('receptionist');

    $response = actingAs($user)->get(sprintf(
        'http://%s/dashboard',
        tenantDomain($tenant),
    ));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('widgets')
            ->where('widgets.0.key', 'today'),
        );
});

it('renders manager widgets on the dashboard', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('dashboard-manager');

    $user->assignRole('manager');

    $response = actingAs($user)->get(sprintf(
        'http://%s/dashboard',
        tenantDomain($tenant),
    ));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('widgets')
            ->where('widgets.8.key', 'revenues'),
        );
});
