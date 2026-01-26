<?php

require_once __DIR__.'/FolioTestHelpers.php';

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

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

it('renders the frontdesk dashboard page', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('frontdesk-page');

    $user->assignRole('owner');

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/frontdesk/dashboard',
        tenantDomain($tenant),
    ));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FrontDesk')
            ->has('reservationsData')
            ->has('roomBoardData'));
});
