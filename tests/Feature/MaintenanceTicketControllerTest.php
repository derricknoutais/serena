<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\MaintenanceTicket;
use App\Models\MaintenanceType;
use App\Models\Room;
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

it('creates maintenance tickets without forcing out of order', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'room' => $room,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('maintenance-create');

    $user->assignRole('manager');

    $response = actingAs($user)->postJson(sprintf(
        'http://%s/maintenance-tickets',
        tenantDomain($tenant),
    ), [
        'room_id' => $room->id,
        'title' => 'Climatisation en panne',
        'severity' => MaintenanceTicket::SEVERITY_HIGH,
        'description' => 'La climatisation ne démarre pas.',
    ]);

    $response->assertSuccessful();

    $ticket = MaintenanceTicket::query()->firstOrFail();

    expect($ticket->blocks_sale)->toBeTrue();
    expect($room->fresh()->status)->toBe(Room::STATUS_AVAILABLE);
    expect($room->fresh()->block_sale_after_checkout)->toBeFalse();
});

it('flags occupied rooms to block sale after checkout when needed', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'room' => $room,
    ] = setupReservationEnvironment('maintenance-occupied');

    $user->assignRole('manager');

    $room->update([
        'status' => Room::STATUS_OCCUPIED,
    ]);

    $response = actingAs($user)->postJson(sprintf(
        'http://%s/maintenance-tickets',
        tenantDomain($tenant),
    ), [
        'room_id' => $room->id,
        'title' => 'Panne électrique',
        'severity' => MaintenanceTicket::SEVERITY_HIGH,
        'blocks_sale' => true,
    ]);

    $response->assertSuccessful();

    $room->refresh();
    expect($room->status)->toBe(Room::STATUS_OCCUPIED);
    expect($room->block_sale_after_checkout)->toBeTrue();
});

it('closes maintenance tickets via the close endpoint', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'room' => $room,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('maintenance-close');

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
        'title' => 'Fuite mineure',
        'opened_at' => now(),
    ]);

    $response = actingAs($user)->patchJson(sprintf(
        'http://%s/maintenance-tickets/%s/close',
        tenantDomain($tenant),
        $ticket->id,
    ), [
        'closed_at' => now()->subHour()->toDateTimeString(),
    ]);

    $response->assertSuccessful();

    $ticket->refresh();
    expect($ticket->status)->toBe(MaintenanceTicket::STATUS_CLOSED);
    expect($ticket->closed_by_user_id)->toBe($user->id);
    expect($ticket->closed_at)->not->toBeNull();
});
