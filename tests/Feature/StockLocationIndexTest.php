<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\StorageLocation;
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

it('loads stock locations even when no stock is recorded', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupReservationEnvironment('stock-location-index');

    $user->assignRole('manager');

    StorageLocation::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Bar',
        'category' => 'bar',
        'is_active' => true,
    ]);

    $response = actingAs($user)->get(sprintf(
        'http://%s/stock/locations',
        tenantDomain($tenant),
    ));

    $response->assertSuccessful();
});
