<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\BarOrder;
use App\Models\BarTable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

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

it('opens a bar order for a table and returns the existing one', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupReservationEnvironment('bar-open');

    $user->assignRole('receptionist');

    $table = BarTable::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Table 1',
        'area' => 'Salle',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $first = $this->actingAs($user)->postJson(sprintf(
        'http://%s/bar/orders/open-for-table',
        tenantDomain($tenant),
    ), [
        'bar_table_id' => $table->id,
    ]);

    $first->assertCreated();

    $firstId = $first->json('order.id');

    $second = $this->actingAs($user)->postJson(sprintf(
        'http://%s/bar/orders/open-for-table',
        tenantDomain($tenant),
    ), [
        'bar_table_id' => $table->id,
    ]);

    $second->assertOk();

    expect($second->json('order.id'))->toBe($firstId);
});

it('prevents moving a bar order to a table with an open order', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupReservationEnvironment('bar-move');

    $user->assignRole('manager');

    $tableOne = BarTable::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Table 1',
        'area' => 'Salle',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $tableTwo = BarTable::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Table 2',
        'area' => 'Salle',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $tableThree = BarTable::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Table 3',
        'area' => 'Salle',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $order = BarOrder::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'bar_table_id' => $tableOne->id,
        'status' => BarOrder::STATUS_OPEN,
        'opened_at' => now(),
        'cashier_user_id' => $user->id,
    ]);

    $move = $this->actingAs($user)->patchJson(sprintf(
        'http://%s/bar/orders/%s/move-table',
        tenantDomain($tenant),
        $order->id,
    ), [
        'bar_table_id' => $tableTwo->id,
    ]);

    $move->assertOk();

    expect($order->refresh()->bar_table_id)->toBe($tableTwo->id);

    BarOrder::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'bar_table_id' => $tableThree->id,
        'status' => BarOrder::STATUS_OPEN,
        'opened_at' => now(),
        'cashier_user_id' => $user->id,
    ]);

    $conflict = $this->actingAs($user)->patchJson(sprintf(
        'http://%s/bar/orders/%s/move-table',
        tenantDomain($tenant),
        $order->id,
    ), [
        'bar_table_id' => $tableThree->id,
    ]);

    $conflict->assertUnprocessable();
    $conflict->assertJsonValidationErrors(['bar_table_id']);
});
