<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Notifications\Channels\TenantDatabaseChannel;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\DatabaseManager;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Gate;
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
        $this->app->make(ChannelManager::class)->extend('tenant_database', function ($app) {
            return new TenantDatabaseChannel($app->make(DatabaseManager::class));
        });

        $this->registerPermissionGates();

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
        $permissions = [
            'reservations.override_datetime',
            'folio_items.void',
            'housekeeping.mark_inspected',
            'housekeeping.mark_clean',
            'housekeeping.mark_dirty',
            'cash_sessions.open',
            'cash_sessions.close',
            'rooms.view', 'rooms.create', 'rooms.update', 'rooms.delete',
            'room_types.view', 'room_types.create', 'room_types.update', 'room_types.delete',
            'offers.view', 'offers.create', 'offers.update', 'offers.delete',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'product_categories.view', 'product_categories.create', 'product_categories.update', 'product_categories.delete',
            'taxes.view', 'taxes.create', 'taxes.update', 'taxes.delete',
            'payment_methods.view', 'payment_methods.create', 'payment_methods.update', 'payment_methods.delete',
            'maintenance_tickets.view', 'maintenance_tickets.create', 'maintenance_tickets.update', 'maintenance_tickets.close',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
            'pos.view', 'pos.create',
            'night_audit.view', 'night_audit.export',
        ];

        foreach ($permissions as $permission) {
            Gate::define($permission, static fn ($user): bool => $user?->hasPermissionTo($permission) ?? false);
        }
    }
}
