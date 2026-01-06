<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\HousekeepingTask;
use App\Models\Room;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
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

it('lists pending and in-progress housekeeping tasks on the housekeeping index', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'roomType' => $roomType,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('hk-index');

    $user->assignRole('housekeeping');

    $secondRoom = Room::query()->create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '202',
        'floor' => '2',
        'status' => Room::STATUS_AVAILABLE,
        'hk_status' => 'dirty',
    ]);

    HousekeepingTask::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'type' => HousekeepingTask::TYPE_CLEANING,
        'status' => HousekeepingTask::STATUS_PENDING,
        'priority' => HousekeepingTask::PRIORITY_NORMAL,
        'created_from' => HousekeepingTask::SOURCE_CHECKOUT,
    ]);

    HousekeepingTask::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $secondRoom->id,
        'type' => HousekeepingTask::TYPE_CLEANING,
        'status' => HousekeepingTask::STATUS_IN_PROGRESS,
        'priority' => HousekeepingTask::PRIORITY_HIGH,
        'created_from' => HousekeepingTask::SOURCE_CHECKOUT,
        'started_at' => now(),
    ]);

    HousekeepingTask::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $secondRoom->id,
        'type' => HousekeepingTask::TYPE_CLEANING,
        'status' => HousekeepingTask::STATUS_DONE,
        'priority' => HousekeepingTask::PRIORITY_NORMAL,
        'created_from' => HousekeepingTask::SOURCE_CHECKOUT,
        'started_at' => now()->subHour(),
        'ended_at' => now()->subMinutes(10),
    ]);

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/housekeeping',
        tenantDomain($tenant),
    ));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Housekeeping/Index')
            ->has('tasks', 2)
            ->has('tasks.0.participants')
            ->has('tasks.0.room')
            ->where('tasks', function ($tasks): bool {
                $statuses = collect($tasks)->pluck('status')->sort()->values()->all();

                return $statuses === [
                    HousekeepingTask::STATUS_IN_PROGRESS,
                    HousekeepingTask::STATUS_PENDING,
                ];
            }));
});

it('shares housekeeping access flags', function (): void {
    ['tenant' => $tenant, 'user' => $user] = setupReservationEnvironment('hk-nav');

    $user->assignRole('housekeeping');

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/housekeeping',
        tenantDomain($tenant),
    ));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.housekeeping_view', true)
            ->where('auth.can.frontdesk_view', false));
});
