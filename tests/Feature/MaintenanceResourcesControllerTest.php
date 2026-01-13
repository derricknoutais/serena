<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\MaintenanceType;
use App\Models\Technician;
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

it('allows managers to create maintenance types', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('maintenance-type');

    $user->assignRole('manager');

    $response = actingAs($user)->post(sprintf(
        'http://%s/settings/resources/maintenance-types',
        tenantDomain($tenant),
    ), [
        'name' => 'Plomberie',
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $type = MaintenanceType::query()->firstOrFail();
    expect($type->name)->toBe('Plomberie');
});

it('allows managers to create technicians', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('maintenance-tech');

    $user->assignRole('manager');

    $response = actingAs($user)->post(sprintf(
        'http://%s/settings/resources/technicians',
        tenantDomain($tenant),
    ), [
        'name' => 'Tech Test',
        'phone' => '699000000',
        'email' => 'tech@example.com',
        'company_name' => 'Service Express',
        'is_internal' => false,
        'notes' => 'Disponible le matin',
    ]);

    $response->assertRedirect();

    $technician = Technician::query()->firstOrFail();
    expect($technician->name)->toBe('Tech Test');
});

it('blocks users without permission from managing maintenance resources', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('maintenance-no-access');

    $user->assignRole('receptionist');

    $response = actingAs($user)->post(sprintf(
        'http://%s/settings/resources/maintenance-types',
        tenantDomain($tenant),
    ), [
        'name' => 'Électricité',
        'is_active' => true,
    ]);

    $response->assertForbidden();
});
