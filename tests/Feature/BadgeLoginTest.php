<?php

require_once __DIR__.'/FolioTestHelpers.php';

use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);
});

it('logs in with badge and pin', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('badge-login');

    $user->forceFill([
        'badge_code' => 'BADGE123',
        'badge_pin' => Hash::make('1234'),
    ])->save();

    $response = $this->post(sprintf(
        'http://%s/login/badge',
        tenantDomain($tenant),
    ), [
        'badge_code' => 'BADGE123',
        'pin' => '1234',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

it('rejects badge login when pin is invalid', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('badge-login-invalid');

    $user->forceFill([
        'badge_code' => 'BADGE999',
        'badge_pin' => Hash::make('9876'),
    ])->save();

    $response = $this->post(sprintf(
        'http://%s/login/badge',
        tenantDomain($tenant),
    ), [
        'badge_code' => 'BADGE999',
        'pin' => '1111',
    ]);

    $response->assertSessionHasErrors(['pin']);
    $this->assertGuest();
});
