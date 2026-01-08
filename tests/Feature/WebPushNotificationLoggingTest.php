<?php

use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\GenericPushNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;
use NotificationChannels\WebPush\WebPushMessage;
use Ramsey\Uuid\Uuid;

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

function createTenantForWebpush(string $slug): Tenant
{
    $tenant = Tenant::query()->create([
        'id' => $slug,
        'slug' => $slug,
        'data' => ['name' => ucfirst($slug)],
    ]);

    $tenant->createDomain(['domain' => "{$slug}.serena.test"]);

    return $tenant;
}

test('webpush tag is unique per notification', function (): void {
    $tenant = createTenantForWebpush('push-tag');
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    Str::createUuidsUsing(fn () => Uuid::fromString('11111111-1111-1111-1111-111111111111'));

    $notification = new GenericPushNotification(
        title: 'Test',
        body: 'Body',
        url: '/dashboard',
        icon: null,
        badge: null,
        tag: null,
        tenantId: $tenant->id,
        hotelId: null,
    );

    $message = $notification->toWebPush($user, $notification);
    $payload = $message->toArray();

    expect($payload['tag'])->toBe('tenant-'.$tenant->id.'-11111111-1111-1111-1111-111111111111');

    Str::createUuidsUsing(null);
});

test('logs are written when a webpush is sent or fails', function (): void {
    Log::spy();

    $tenant = createTenantForWebpush('push-log');
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $subscription = PushSubscription::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
    ]);

    $message = (new WebPushMessage)->title('Test')->body('Body');

    $successReport = new MessageSentReport(
        new PsrRequest('POST', $subscription->endpoint),
        new PsrResponse(201),
        true,
        'OK',
    );

    event(new NotificationSent($successReport, $subscription, $message));

    Log::shouldHaveReceived('info')->with('webpush.sent', \Mockery::on(function (array $context) use ($subscription): bool {
        return $context['subscription_id'] === $subscription->id
            && $context['tenant_id'] === $subscription->tenant_id
            && $context['user_id'] === $subscription->user_id;
    }));

    $failedReport = new MessageSentReport(
        new PsrRequest('POST', $subscription->endpoint),
        new PsrResponse(410),
        false,
        'Gone',
    );

    event(new NotificationFailed($failedReport, $subscription, $message));

    Log::shouldHaveReceived('warning')->with('webpush.failed', \Mockery::on(function (array $context) use ($subscription): bool {
        return $context['subscription_id'] === $subscription->id
            && $context['tenant_id'] === $subscription->tenant_id
            && $context['user_id'] === $subscription->user_id
            && $context['expired'] === true;
    }));
});
