<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\MaintenanceTicket;
use App\Models\Room;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $this->seed(RoleSeeder::class);
});

it('creates a maintenance ticket and marks the room out of service', function (): void {
    [
        'tenant' => $tenant,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('maintenance-create');

    $user->assignRole('receptionist');

    $response = $this->actingAs($user)->postJson(sprintf(
        'http://%s/maintenance-tickets',
        tenantDomain($tenant),
    ), [
        'room_id' => $room->id,
        'title' => 'Climatisation en panne',
        'severity' => MaintenanceTicket::SEVERITY_HIGH,
        'description' => 'Le moteur ne démarre plus.',
    ]);

    $response->assertOk()
        ->assertJsonPath('ticket.status', MaintenanceTicket::STATUS_OPEN)
        ->assertJsonPath('ticket.severity', MaintenanceTicket::SEVERITY_HIGH);

    expect($room->fresh()->status)->toBe(Room::STATUS_OUT_OF_ORDER);
    expect(MaintenanceTicket::query()->count())->toBe(1);
});

it('prevents creating a second active ticket for the same room', function (): void {
    [
        'tenant' => $tenant,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('maintenance-duplicate');

    $user->assignRole('receptionist');

    MaintenanceTicket::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_OPEN,
        'severity' => MaintenanceTicket::SEVERITY_MEDIUM,
        'title' => 'Fuite',
        'description' => null,
        'opened_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson(sprintf(
        'http://%s/maintenance-tickets',
        tenantDomain($tenant),
    ), [
        'room_id' => $room->id,
        'title' => 'Autre souci',
        'severity' => MaintenanceTicket::SEVERITY_LOW,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['room_id']);
});

it('allows a manager to resolve a ticket and restore the room', function (): void {
    [
        'tenant' => $tenant,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('maintenance-resolve');

    $user->assignRole('manager');
    $room->update(['status' => Room::STATUS_OUT_OF_ORDER, 'hk_status' => 'clean']);

    $ticket = MaintenanceTicket::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_OPEN,
        'severity' => MaintenanceTicket::SEVERITY_MEDIUM,
        'title' => 'Porte cassée',
        'description' => null,
        'opened_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($user)->patchJson(sprintf(
        'http://%s/maintenance-tickets/%s',
        tenantDomain($tenant),
        $ticket->id,
    ), [
        'status' => MaintenanceTicket::STATUS_RESOLVED,
        'restore_room_status' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('ticket.status', MaintenanceTicket::STATUS_RESOLVED);

    $ticket->refresh();
    $room->refresh();

    expect($ticket->closed_at)->not()->toBeNull();
    expect($room->status)->toBe(Room::STATUS_AVAILABLE);
    expect($room->hk_status)->toBe('dirty');
});

it('forbids receptionists from closing a maintenance ticket', function (): void {
    [
        'tenant' => $tenant,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('maintenance-forbid');

    $user->assignRole('receptionist');

    $ticket = MaintenanceTicket::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_OPEN,
        'severity' => MaintenanceTicket::SEVERITY_MEDIUM,
        'title' => 'Fenêtre cassée',
        'description' => null,
        'opened_at' => now(),
    ]);

    $response = $this->actingAs($user)->patchJson(sprintf(
        'http://%s/maintenance-tickets/%s',
        tenantDomain($tenant),
        $ticket->id,
    ), [
        'status' => MaintenanceTicket::STATUS_RESOLVED,
    ]);

    $response->assertStatus(403);
});

it('shows open and in-progress tickets by default on the maintenance index', function (): void {
    [
        'tenant' => $tenant,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('maintenance-index');

    $user->assignRole('manager');

    $openTicket = MaintenanceTicket::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_OPEN,
        'severity' => MaintenanceTicket::SEVERITY_LOW,
        'title' => 'Fenêtre fissurée',
        'description' => null,
        'opened_at' => now()->subDay(),
    ]);

    $inProgressTicket = MaintenanceTicket::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_IN_PROGRESS,
        'severity' => MaintenanceTicket::SEVERITY_MEDIUM,
        'title' => 'Climatisation',
        'description' => null,
        'opened_at' => now()->subHours(6),
    ]);

    MaintenanceTicket::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_RESOLVED,
        'severity' => MaintenanceTicket::SEVERITY_HIGH,
        'title' => 'Porte réparée',
        'description' => null,
        'opened_at' => now()->subHours(3),
    ]);

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/maintenance',
        tenantDomain($tenant),
    ));

    $response->assertOk()
        ->assertInertia(function (Assert $page) use ($openTicket, $inProgressTicket): void {
            $page->component('Maintenance/Index')
                ->where('filters.status', 'open')
                ->has('tickets.data', 2)
                ->where('tickets.data', function ($data) use ($openTicket, $inProgressTicket): bool {
                    $ids = collect($data)->pluck('id')->all();

                    return in_array($openTicket->id, $ids, true)
                        && in_array($inProgressTicket->id, $ids, true);
                });
        });
});

it('filters maintenance tickets by status', function (): void {
    [
        'tenant' => $tenant,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('maintenance-index-filter');

    $user->assignRole('manager');

    MaintenanceTicket::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_OPEN,
        'severity' => MaintenanceTicket::SEVERITY_LOW,
        'title' => 'Poignée',
        'description' => null,
        'opened_at' => now()->subHour(),
    ]);

    $resolved = MaintenanceTicket::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $room->hotel_id,
        'room_id' => $room->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_RESOLVED,
        'severity' => MaintenanceTicket::SEVERITY_MEDIUM,
        'title' => 'TV',
        'description' => null,
        'opened_at' => now()->subMinutes(30),
    ]);

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/maintenance?status=resolved',
        tenantDomain($tenant),
    ));

    $response->assertOk()
        ->assertInertia(function (Assert $page) use ($resolved): void {
            $page->component('Maintenance/Index')
                ->where('filters.status', 'resolved')
                ->has('tickets.data', 1)
                ->where('tickets.data.0.id', $resolved->id);
        });
});
