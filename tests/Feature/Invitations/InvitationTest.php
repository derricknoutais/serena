<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvitationCreated;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'app.url' => 'http://saas-template.test',
        'app.url_host' => 'saas-template.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['saas-template.test'],
    ]);

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

function createTenantWithDomain(string $id = 'orchid'): Tenant
{
    $tenant = Tenant::create([
        'id' => $id,
        'slug' => $id,
        'data' => ['name' => ucfirst($id)],
    ]);

    $tenant->createDomain(['domain' => "{$id}.saas-template.test"]);

    return $tenant;
}

test('tenant admin can send an invitation email', function () {
    Notification::fake();

    $tenant = createTenantWithDomain('orisha');
    tenancy()->initialize($tenant);

    $admin = User::factory()->withoutTwoFactor()->create([
        'tenant_id' => $tenant->id,
        'email' => 'admin@orisha.test',
    ]);

    $response = $this->actingAs($admin)->post('http://'.$tenant->domains()->value('domain').'/invitations', [
        'email' => 'invitee@example.com',
    ]);

    $response->assertRedirect();

    $invitation = Invitation::query()->first();
    expect($invitation)->not->toBeNull();
    expect($invitation->tenant_id)->toBe($tenant->id);
    expect($invitation->email)->toBe('invitee@example.com');
    expect($invitation->accepted_at)->toBeNull();

    Notification::assertSentOnDemandTimes(InvitationCreated::class, 1);
});

test('invited user can view accept invitation page on tenant domain', function () {
    $tenant = createTenantWithDomain('luna');
    tenancy()->initialize($tenant);

    $token = Str::random(40);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'guest@luna.test',
        'token' => hash('sha256', $token),
    ]);

    $response = $this->get('http://'.$tenant->domains()->value('domain').'/invitations/accept?token='.$token.'&email='.$invitation->email);

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('invitations/Accept')
        ->where('email', $invitation->email));
});

test('invited user can accept and is logged in', function () {
    $tenant = createTenantWithDomain('nova');
    tenancy()->initialize($tenant);

    $token = Str::random(40);
    $invitation = Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'new.user@example.com',
        'token' => hash('sha256', $token),
        'expires_at' => now()->addDay(),
    ]);

    $response = $this->post('http://'.$tenant->domains()->value('domain').'/invitations/accept', [
        'token' => $token,
        'email' => $invitation->email,
        'name' => 'New User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
    expect(auth()->user()->tenant_id)->toBe($tenant->id);
    expect(auth()->user()->email)->toBe($invitation->email);

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull();
});

test('expired invitations are rejected', function () {
    $tenant = createTenantWithDomain('old');
    tenancy()->initialize($tenant);

    $token = Str::random(40);
    Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'late@example.com',
        'token' => hash('sha256', $token),
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->get('http://'.$tenant->domains()->value('domain').'/invitations/accept?token='.$token.'&email=late@example.com');

    $response->assertNotFound();
});
