<?php

namespace App\Providers;

use App\Models\HousekeepingChecklist;
use App\Models\Tenant;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Policies\HousekeepingChecklistPolicy;
use App\Support\PermissionsCatalog;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\DatabaseManager;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(ChannelManager::class)->extend('tenant_database', function ($app) {
            return new TenantDatabaseChannel($app->make(DatabaseManager::class));
        });

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            $response = $event->report->getResponse();

            Log::info('webpush.sent', [
                'subscription_id' => $event->subscription->id ?? null,
                'tenant_id' => $event->subscription->tenant_id ?? null,
                'user_id' => $event->subscription->user_id ?? null,
                'endpoint' => $event->report->getEndpoint(),
                'status_code' => $response?->getStatusCode(),
                'reason' => $event->report->getReason(),
            ]);
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            $response = $event->report->getResponse();

            Log::warning('webpush.failed', [
                'subscription_id' => $event->subscription->id ?? null,
                'tenant_id' => $event->subscription->tenant_id ?? null,
                'user_id' => $event->subscription->user_id ?? null,
                'endpoint' => $event->report->getEndpoint(),
                'status_code' => $response?->getStatusCode(),
                'reason' => $event->report->getReason(),
                'expired' => $event->report->isSubscriptionExpired(),
            ]);
        });

        Gate::policy(HousekeepingChecklist::class, HousekeepingChecklistPolicy::class);

        PermissionsCatalog::ensureExists();
        $this->registerPermissionGates();

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $mailData = [
                'actionUrl' => $url,
                'verificationUrl' => $url,
                'userName' => $notifiable->name ?? $notifiable->email,
                'logoUrl' => asset('img/serena_logo.png'),
            ];

            $mailMessage = (new MailMessage)
                ->subject('Confirmez votre adresse e-mail');

            $mailMessage->actionUrl = $url;

            return $mailMessage
                ->view('mail.verify-email', $mailData)
                ->text('mail.verify-email-text', $mailData);
        });

        VerifyEmail::createUrlUsing(function ($notifiable) {
            $tenantDomain = Tenant::query()
                ->whereKey($notifiable->tenant_id)
                ->first()
                ?->domains()
                ->value('domain');

            $host = $tenantDomain ?? sprintf(
                '%s.%s',
                Str::slug((string) $notifiable->tenant_id),
                config('app.url_host', 'saas-template.test'),
            );

            $tenantUrl = sprintf('%s://%s', config('app.url_scheme', 'http'), $host);
            $originalAppUrl = config('app.url');

            URL::forceRootUrl($tenantUrl);
            config(['app.url' => $tenantUrl]);

            $signedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
            );

            URL::forceRootUrl($originalAppUrl);
            config(['app.url' => $originalAppUrl]);

            return $signedUrl;
        });

        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $tenantDomain = Tenant::query()
                ->whereKey($notifiable->tenant_id)
                ->first()
                ?->domains()
                ->value('domain');

            $host = $tenantDomain ?? sprintf(
                '%s.%s',
                Str::slug((string) $notifiable->tenant_id),
                config('app.url_host', 'saas-template.test'),
            );

            $tenantUrl = sprintf('%s://%s', config('app.url_scheme', 'http'), $host);
            $originalAppUrl = config('app.url');

            URL::forceRootUrl($tenantUrl);
            config(['app.url' => $tenantUrl]);

            $url = URL::route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            URL::forceRootUrl($originalAppUrl);
            config(['app.url' => $originalAppUrl]);

            return $url;
        });
    }

    private function registerPermissionGates(): void
    {
        foreach (PermissionsCatalog::all() as $permission) {
            Gate::define($permission, static fn ($user): bool => $user?->checkPermissionTo($permission) ?? false);
        }
    }
}
