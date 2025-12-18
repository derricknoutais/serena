<?php

use App\Models\Tenant;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Database\Models\Domain;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config([
        'app.url' => 'http://saas-template.test',
        'app.url_host' => 'saas-template.test',
        'fortify.domain' => 'saas-template.test',
        'tenancy.central_domains' => ['saas-template.test'],
        'session.domain' => '.saas-template.test',
    ]);
});

test('registration screen can be rendered on central domain', function () {
    $response = $this->get('http://saas-template.test/register');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/Register'));
});

test('new tenants and admin users can register', function () {
    Notification::fake();

    $response = $this->post('http://saas-template.test/register', [
        'business_name' => 'Acme Hotels',
        'tenant_slug' => 'acme-hotels',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('http://acme-hotels.saas-template.test/email/verify');
    $this->assertAuthenticated();

    $tenantId = Domain::where('domain', 'acme-hotels.saas-template.test')->value('tenant_id');
    $tenant = Tenant::find($tenantId);

    expect($tenant)->not->toBeNull();
    expect(Domain::where('domain', 'acme-hotels.saas-template.test')->exists())->toBeTrue();
    expect(auth()->user()->tenant_id)->toBe($tenantId);

    Notification::assertSentTo(auth()->user(), VerifyEmail::class, function (VerifyEmail $notification) {
        $mail = $notification->toMail(auth()->user());

        expect($mail->subject)->toBe('Confirmez votre adresse e-mail');
        expect($mail->view)->toBe('mail.verify-email');
        expect($mail->viewData['userName'])->toBe('Ada Lovelace');
        expect($mail->viewData['logoUrl'])->toContain('/img/serena_logo.png');

        return str_contains($mail->viewData['verificationUrl'], 'http://acme-hotels.saas-template.test/email/verify');
    });
});

test('tenant slug defaults to business name', function () {
    $response = $this->post('http://saas-template.test/register', [
        'business_name' => 'Bright Studio',
        'name' => 'Bria Hughes',
        'email' => 'bria@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('http://bright-studio.saas-template.test/email/verify');
    expect(Domain::where('domain', 'bright-studio.saas-template.test')->exists())->toBeTrue();
});

test('verification link is signed for tenant domain', function () {
    Notification::fake();

    $this->post('http://saas-template.test/register', [
        'business_name' => 'Orbit Labs',
        'tenant_slug' => 'orbit-labs',
        'name' => 'Olive Stone',
        'email' => 'olive@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = auth()->user();
    $notification = Notification::sent($user, VerifyEmail::class)->first();

    $url = $notification->toMail($user)->actionUrl;

    actingAs($user);

    $response = get($url);

    $response->assertRedirect('http://orbit-labs.saas-template.test/dashboard');
    $this->assertTrue($user->fresh()->hasVerifiedEmail());
});
