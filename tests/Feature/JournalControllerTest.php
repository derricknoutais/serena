<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Activity;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

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

it('blocks users without journal permission', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('journal-blocked');

    $user->assignRole('receptionist');

    $response = actingAs($user)->get(sprintf(
        'http://%s/journal',
        tenantDomain($tenant),
    ));

    $response->assertForbidden();
});

it('shows activity journal for managers', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupReservationEnvironment('journal-index');

    $user->assignRole('manager');

    Activity::query()->create([
        'log_name' => 'reservation',
        'description' => 'confirmed',
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'causer_type' => get_class($user),
        'causer_id' => $user->id,
        'subject_type' => null,
        'subject_id' => null,
        'properties' => [],
    ]);

    $response = actingAs($user)->get(sprintf(
        'http://%s/journal',
        tenantDomain($tenant),
    ));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Journal/Index')
            ->has('activities.data', 1)
            ->has('users')
            ->has('moduleOptions')
        );
});
