<?php

namespace App\Providers;

use App\Models\Tenant;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
}
