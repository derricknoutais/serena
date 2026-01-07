<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

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

it('downloads the badge qr svg for a tenant user', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('badge-download');

    $user->assignRole('owner');

    $targetUser = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);
    $targetUser->forceFill([
        'badge_code' => 'BADGE-DL',
        'badge_pin' => Hash::make('1234'),
    ])->save();

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/settings/badges/%s/download',
        tenantDomain($tenant),
        $targetUser->id,
    ));

    $response->assertOk();
    $response->assertHeader('content-type', 'image/svg+xml');
    expect($response->streamedContent())->toContain('<svg');
});

it('returns not found when the badge code is missing', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('badge-download-missing');

    $user->assignRole('owner');

    $targetUser = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/settings/badges/%s/download',
        tenantDomain($tenant),
        $targetUser->id,
    ));

    $response->assertNotFound();
});
