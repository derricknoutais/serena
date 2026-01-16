<?php

require_once __DIR__.'/../FolioTestHelpers.php';

use App\Models\BarTable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

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

it('allows managers to create bar tables from settings resources', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('bar-table-config');

    $user->assignRole('manager');

    $response = actingAs($user)->post(sprintf(
        'http://%s/settings/resources/bar-tables',
        tenantDomain($tenant),
    ), [
        'name' => 'Table 12',
        'area' => 'Terrasse',
        'capacity' => 4,
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $response->assertRedirect();

    $table = BarTable::query()->firstOrFail();
    expect($table->name)->toBe('Table 12');
});

it('blocks users without permission from managing bar tables', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('bar-table-config-forbidden');

    $user->assignRole('receptionist');

    $response = actingAs($user)->post(sprintf(
        'http://%s/settings/resources/bar-tables',
        tenantDomain($tenant),
    ), [
        'name' => 'Table 99',
        'area' => 'Salle',
        'capacity' => 2,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $response->assertForbidden();
});
