<?php

use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\GenericPushNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

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

if (! function_exists('createTenantWithDomain')) {
    function createTenantWithDomain(string $slug): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => $slug,
            'slug' => $slug,
            'data' => ['name' => ucfirst($slug)],
        ]);

        $tenant->createDomain(['domain' => "{$slug}.serena.test"]);

        return $tenant;
    }
}

test('stores a push subscription for guests', function (): void {
    $tenant = createTenantWithDomain('push-guest');

    $payload = [
        'endpoint' => sprintf('https://push.example.com/%s', Str::uuid()),
        'keys' => [
            'p256dh' => base64_encode('p256dh-key'),
            'auth' => base64_encode('auth-token'),
        ],
        'contentEncoding' => 'aesgcm',
        'userAgent' => 'TestBrowser/1.0',
    ];

    $response = $this->post('http://'.$tenant->domains()->value('domain').'/push/subscribe', $payload);

    $response->assertOk();

    $subscription = PushSubscription::query()->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->tenant_id)->toBe($tenant->id);
    expect($subscription->user_id)->toBeNull();
    expect($subscription->endpoint)->toBe($payload['endpoint']);
});

test('attaches the subscription to the current user on subscribe', function (): void {
    $tenant = createTenantWithDomain('push-user');
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $endpoint = sprintf('https://push.example.com/%s', Str::uuid());

    $subscription = PushSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => null,
        'endpoint' => $endpoint,
    ]);

    $payload = [
        'endpoint' => $endpoint,
        'keys' => [
            'p256dh' => base64_encode('p256dh-key'),
            'auth' => base64_encode('auth-token'),
        ],
        'contentEncoding' => 'aesgcm',
        'userAgent' => 'TestBrowser/1.0',
    ];

    $response = $this->actingAs($user)->post(
        'http://'.$tenant->domains()->value('domain').'/push/subscribe',
        $payload,
    );

    $response->assertOk();
    expect($subscription->refresh()->user_id)->toBe($user->id);
});

test('removes a subscription on unsubscribe', function (): void {
    $tenant = createTenantWithDomain('push-unsubscribe');
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $subscription = PushSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(
        'http://'.$tenant->domains()->value('domain').'/push/unsubscribe',
        [
            'endpoint' => $subscription->endpoint,
        ],
    );

    $response->assertOk();
    expect(PushSubscription::query()->whereKey($subscription->id)->exists())->toBeFalse();
});

test('only managers or owners can send a test push', function (): void {
    Notification::fake();

    $tenant = createTenantWithDomain('push-test');
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->post(
        'http://'.$tenant->domains()->value('domain').'/push/test',
    );

    $response->assertForbidden();
    Notification::assertNothingSent();

    $user->assignRole('manager');

    $response = $this->actingAs($user)->post(
        'http://'.$tenant->domains()->value('domain').'/push/test',
    );

    $response->assertOk();
    Notification::assertSentTo($user, GenericPushNotification::class);
});
