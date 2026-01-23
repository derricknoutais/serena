<?php

require_once __DIR__.'/FolioTestHelpers.php';

use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $this->seed(PermissionSeeder::class);
});

it('exposes frontdesk and housekeeping permissions for the navbar', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('nav-permissions');

    $user->givePermissionTo('frontdesk.view', 'housekeeping.view');

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/housekeeping',
        tenantDomain($tenant),
    ));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Housekeeping/Index')
            ->where('auth.can.frontdesk_view', true)
            ->where('auth.can.housekeeping_view', true)
        );
});

it('exposes hotel legal details for the layout footer', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('nav-legal');

    $user->givePermissionTo('housekeeping.view');

    $hotel->update([
        'document_settings' => [
            'legal' => [
                'nif' => 'M123456789',
                'rccm' => 'RC/DLA/2024/B/12345',
            ],
        ],
    ]);

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/housekeeping',
        tenantDomain($tenant),
    ));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Housekeeping/Index')
            ->where('auth.activeHotel.document_settings.legal.nif', 'M123456789')
            ->where('auth.activeHotel.document_settings.legal.rccm', 'RC/DLA/2024/B/12345')
        );
});
