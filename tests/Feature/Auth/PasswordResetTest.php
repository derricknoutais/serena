<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;

beforeEach(function () {
    config([
        'app.url' => 'http://saas-template.test',
        'app.url_host' => 'saas-template.test',
        'tenancy.central_domains' => ['saas-template.test'],
        'session.domain' => '.saas-template.test',
    ]);
});

function createTenant(string $id): Tenant
{
    $tenant = Tenant::create([
        'id' => $id,
        'data' => ['name' => ucfirst($id)],
    ]);

    $tenant->createDomain(['domain' => "{$id}.saas-template.test"]);

    return $tenant;
}

test('reset password link screen can be rendered on tenant domain', function () {
    $tenant = createTenant('orchid');

    $response = $this->get("http://{$tenant->domains()->value('domain')}/forgot-password");

    $response->assertStatus(200);
});

test('reset password link can be requested from tenant domain', function () {
    Notification::fake();
    $tenant = createTenant('lilac');

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'lilac@example.com',
    ]);

    $this->post("http://{$tenant->domains()->value('domain')}/forgot-password", ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($tenant, $user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_contains($url, "http://{$tenant->domains()->value('domain')}/reset-password");
    });
});

test('reset password screen can be rendered on tenant domain', function () {
    Notification::fake();
    $tenant = createTenant('daisy');

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'daisy@example.com',
    ]);

    $this->post("http://{$tenant->domains()->value('domain')}/forgot-password", ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        $response = get($url);

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token on tenant domain', function () {
    Notification::fake();
    $tenant = createTenant('tulip');

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'tulip@example.com',
    ]);

    $this->post("http://{$tenant->domains()->value('domain')}/forgot-password", ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($tenant, $user) {
        $url = $notification->toMail($user)->actionUrl;

        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $response = $this->post("http://{$tenant->domains()->value('domain')}/reset-password", [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect("http://{$tenant->domains()->value('domain')}/login");

        return true;
    });
});

test('password cannot be reset across tenants', function () {
    $tenantA = createTenant('rose');
    $tenantB = createTenant('violet');

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'email' => 'rose@example.com',
    ]);

    Notification::fake();
    $this->post("http://{$tenantA->domains()->value('domain')}/forgot-password", ['email' => $userA->email]);

    $notification = Notification::sent($userA, ResetPassword::class)->first();

    $this->post("http://{$tenantB->domains()->value('domain')}/reset-password", [
        'token' => $notification->token,
        'email' => $userA->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertSessionHasErrors('email');
});
