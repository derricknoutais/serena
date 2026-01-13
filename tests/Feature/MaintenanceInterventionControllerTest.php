<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceTicket;
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

it('forbids users without permission from creating interventions', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('intervention-forbidden');

    $user->assignRole('housekeeping');

    $response = actingAs($user)->postJson(sprintf(
        'http://%s/maintenance/interventions',
        tenantDomain($tenant),
    ), [
        'summary' => 'Test',
    ]);

    $response->assertForbidden();
});

it('creates interventions and attaches tickets', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'room' => $room,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('intervention-create');

    $user->assignRole('manager');

    $type = MaintenanceType::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Autre',
        'is_active' => true,
    ]);

    $ticket = MaintenanceTicket::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'maintenance_type_id' => $type->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_OPEN,
        'severity' => MaintenanceTicket::SEVERITY_MEDIUM,
        'blocks_sale' => false,
        'title' => 'Plomberie',
        'opened_at' => now(),
    ]);

    $technician = Technician::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Jean Dupont',
        'is_internal' => true,
    ]);

    $response = actingAs($user)->postJson(sprintf(
        'http://%s/maintenance/interventions',
        tenantDomain($tenant),
    ), [
        'technician_id' => $technician->id,
        'summary' => 'Intervention plomberie',
        'labor_cost' => 1500,
        'parts_cost' => 300,
        'currency' => 'XAF',
        'tickets' => [
            [
                'maintenance_ticket_id' => $ticket->id,
                'work_done' => 'Changement robinet',
                'labor_cost' => 1000,
                'parts_cost' => 300,
            ],
        ],
    ]);

    $response->assertSuccessful();

    $intervention = MaintenanceIntervention::query()->firstOrFail();

    expect((float) $intervention->labor_cost)->toBe(1500.0)
        ->and((float) $intervention->parts_cost)->toBe(300.0)
        ->and((float) $intervention->total_cost)->toBe(1800.0);

    expect($intervention->tickets)->toHaveCount(1);
    expect($intervention->tickets->first()->pivot?->work_done)->toBe('Changement robinet');
});

it('submits interventions for accounting', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('intervention-submit');

    $user->assignRole('manager');

    $intervention = MaintenanceIntervention::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'created_by_user_id' => $user->id,
        'labor_cost' => 500,
        'parts_cost' => 0,
        'currency' => 'XAF',
        'accounting_status' => MaintenanceIntervention::STATUS_DRAFT,
    ]);

    $response = actingAs($user)->postJson(sprintf(
        'http://%s/maintenance/interventions/%s/submit',
        tenantDomain($tenant),
        $intervention->id,
    ));

    $response->assertSuccessful();

    $intervention->refresh();
    expect($intervention->accounting_status)->toBe(MaintenanceIntervention::STATUS_SUBMITTED);
    expect($intervention->submitted_to_accounting_at)->not->toBeNull();
});
