<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);
});

it('renders the switch user page with tenant accounts', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('switch-user-page');

    $otherUser = User::factory()->create([
        'tenant_id' => $tenant->id,
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/switch-user',
        tenantDomain($tenant),
    ));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/SwitchUser')
            ->where('currentUserId', $user->id)
            ->has('users', 2)
            ->where('users', fn ($users) => collect($users)->pluck('id')->contains($otherUser->id))
        );
});

it('switches user when the password is valid', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('switch-user-valid');

    $user->forceFill([
        'password' => Hash::make('current-secret'),
    ])->save();

    $targetUser = User::factory()->create([
        'tenant_id' => $tenant->id,
        'password' => Hash::make('target-secret'),
    ]);

    $response = $this->actingAs($user)->post(sprintf(
        'http://%s/switch-user',
        tenantDomain($tenant),
    ), [
        'user_id' => $targetUser->id,
        'password' => 'target-secret',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($targetUser);
});

it('switches user with badge and pin', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('switch-user-badge');

    $targetUser = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);
    $targetUser->forceFill([
        'badge_code' => 'BADGE777',
        'badge_pin' => Hash::make('4321'),
    ])->save();

    $response = $this->actingAs($user)->post(sprintf(
        'http://%s/switch-user/badge',
        tenantDomain($tenant),
    ), [
        'badge_code' => 'BADGE777',
        'pin' => '4321',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($targetUser);
});

it('rejects switching user when the password is invalid', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('switch-user-invalid');

    $user->forceFill([
        'password' => Hash::make('current-secret'),
    ])->save();

    $targetUser = User::factory()->create([
        'tenant_id' => $tenant->id,
        'password' => Hash::make('target-secret'),
    ]);

    $response = $this->actingAs($user)->post(sprintf(
        'http://%s/switch-user',
        tenantDomain($tenant),
    ), [
        'user_id' => $targetUser->id,
        'password' => 'wrong-secret',
    ]);

    $response->assertSessionHasErrors(['password']);
    $this->assertAuthenticatedAs($user);
});
