<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceInterventionCost;
use App\Models\MaintenanceTicket;
use App\Models\MaintenanceType;
use App\Models\Payment;
use App\Models\StockItem;
use App\Models\StockOnHand;
use App\Models\StorageLocation;
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

it('closes tickets when submitting interventions', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'hotel' => $hotel,
        'room' => $room,
    ] = setupReservationEnvironment('intervention-submit-closes-tickets');

    $user->assignRole('manager');

    $type = MaintenanceType::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Plomberie',
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
        'title' => 'Robinet',
        'opened_at' => now(),
    ]);

    $intervention = MaintenanceIntervention::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'created_by_user_id' => $user->id,
        'labor_cost' => 500,
        'parts_cost' => 0,
        'currency' => 'XAF',
        'accounting_status' => MaintenanceIntervention::STATUS_DRAFT,
    ]);

    $intervention->tickets()->attach($ticket->id, [
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'work_done' => 'Fait',
        'labor_cost' => 0,
        'parts_cost' => 0,
    ]);

    $response = actingAs($user)->postJson(sprintf(
        'http://%s/maintenance/interventions/%s/submit',
        tenantDomain($tenant),
        $intervention->id,
    ));

    $response->assertSuccessful();

    $ticket->refresh();

    expect($ticket->status)->toBe(MaintenanceTicket::STATUS_CLOSED)
        ->and($ticket->closed_by_user_id)->toBe($user->id)
        ->and($ticket->closed_at)->not->toBeNull();
});

it('adds a cost line and recalculates estimated totals without creating payments', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('intervention-cost-line');

    $user->assignRole('manager');

    $intervention = MaintenanceIntervention::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'created_by_user_id' => $user->id,
        'currency' => 'XAF',
        'accounting_status' => MaintenanceIntervention::STATUS_DRAFT,
    ]);

    $response = actingAs($user)->postJson(sprintf(
        'http://%s/maintenance/interventions/%s/cost-lines',
        tenantDomain($tenant),
        $intervention->id,
    ), [
        'cost_type' => 'labor',
        'label' => 'Main d’œuvre',
        'quantity' => 2,
        'unit_price' => 1500,
    ]);

    $response->assertSuccessful();

    $intervention->refresh();
    $cost = MaintenanceInterventionCost::query()->first();

    expect($cost)->not->toBeNull()
        ->and((float) $intervention->estimated_total_amount)->toBe(3000.0);

    expect(Payment::query()->count())->toBe(0);
});

it('consumes stock items and updates estimated totals without payments', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('intervention-stock-consume');

    $user->assignRole('manager');

    $intervention = MaintenanceIntervention::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'created_by_user_id' => $user->id,
        'currency' => 'XAF',
        'accounting_status' => MaintenanceIntervention::STATUS_DRAFT,
    ]);

    $location = StorageLocation::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Atelier',
        'category' => 'maintenance',
        'is_active' => true,
    ]);

    $item = StockItem::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Ampoule E27',
        'default_purchase_price' => 500,
        'currency' => 'XAF',
        'is_active' => true,
        'unit' => 'unit',
        'item_category' => 'maintenance',
    ]);

    StockOnHand::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'storage_location_id' => $location->id,
        'stock_item_id' => $item->id,
        'quantity_on_hand' => 5,
    ]);

    $response = actingAs($user)->postJson(sprintf(
        'http://%s/maintenance/interventions/%s/items',
        tenantDomain($tenant),
        $intervention->id,
    ), [
        'stock_item_id' => $item->id,
        'storage_location_id' => $location->id,
        'quantity' => 2,
    ]);

    $response->assertSuccessful();

    $intervention->refresh();

    $onHand = StockOnHand::query()->where('stock_item_id', $item->id)->first();

    expect((float) ($onHand?->quantity_on_hand ?? 0))->toBe(3.0)
        ->and((float) $intervention->estimated_total_amount)->toBe(1000.0);

    expect(Payment::query()->count())->toBe(0);
});

it('ignores unit cost override when permission is missing', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('intervention-unit-cost');

    $user->assignRole('receptionist');

    $intervention = MaintenanceIntervention::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'created_by_user_id' => $user->id,
        'currency' => 'XAF',
        'accounting_status' => MaintenanceIntervention::STATUS_DRAFT,
    ]);

    $location = StorageLocation::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Réserve',
        'category' => 'maintenance',
        'is_active' => true,
    ]);

    $item = StockItem::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Joint téflon',
        'default_purchase_price' => 200,
        'currency' => 'XAF',
        'is_active' => true,
        'unit' => 'unit',
        'item_category' => 'maintenance',
    ]);

    StockOnHand::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'storage_location_id' => $location->id,
        'stock_item_id' => $item->id,
        'quantity_on_hand' => 5,
    ]);

    $response = actingAs($user)->postJson(sprintf(
        'http://%s/maintenance/interventions/%s/items',
        tenantDomain($tenant),
        $intervention->id,
    ), [
        'stock_item_id' => $item->id,
        'storage_location_id' => $location->id,
        'quantity' => 1,
        'unit_cost' => 999,
    ]);

    $response->assertSuccessful();

    $intervention->refresh();
    $entry = $intervention->items()->latest('id')->first();

    expect((float) $entry->unit_cost)->toBe(200.0);
});
