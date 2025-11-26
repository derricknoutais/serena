<?php

namespace App\Providers;

use App\Actions\Auth\RegisterNewTenantAndUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\TenantLoginResponse;
use App\Http\Responses\TenantLogoutResponse;
use App\Http\Responses\TenantRegisterResponse;
use App\Http\Responses\TenantVerifyEmailResponse;
use App\Models\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Stancl\Tenancy\Database\Models\Domain;

class FortifyServiceProvider extends ServiceProvider
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
        $this->configureActions();
        $this->configureViews();
        $this->configureResponses();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(RegisterNewTenantAndUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            if ($this->isCentralDomain($request->getHost())) {
                return Inertia::render('auth/CentralLogin', [
                    'centralDomain' => config('app.url_host'),
                    'tenant' => $request->query('tenant'),
                ]);
            }

            $tenant = $this->resolveTenantFromHost($request->getHost());

            return Inertia::render('auth/Login', [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'canRegister' => Features::enabled(Features::registration()),
                'status' => $request->session()->get('status'),
                'tenant' => $tenant ? [
                    'name' => $tenant->name ?? $tenant->id,
                    'domain' => $tenant->domains()->value('domain'),
                    'slug' => $tenant->id,
                ] : null,
                'centralLoginUrl' => $this->centralLoginUrl(),
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'centralDomain' => config('app.url_host'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    private function configureResponses(): void
    {
        $this->app->singleton(RegisterResponse::class, TenantRegisterResponse::class);
        $this->app->singleton(PasswordResetResponse::class, \App\Http\Responses\TenantPasswordResetResponse::class);
        $this->app->singleton(VerifyEmailResponse::class, TenantVerifyEmailResponse::class);
        $this->app->singleton(LoginResponse::class, TenantLoginResponse::class);
        $this->app->singleton(LogoutResponse::class, TenantLogoutResponse::class);
    }

    private function centralDomain(): string
    {
        foreach (config('tenancy.central_domains', []) as $domain) {
            if (is_string($domain) && $domain !== '') {
                return $domain;
            }
        }

        return config('app.url_host', 'localhost');
    }

    private function centralLoginUrl(?string $tenantSlug = null): string
    {
        $query = $tenantSlug ? '?'.http_build_query(['tenant' => $tenantSlug]) : '';

        return sprintf(
            '%s://%s/login%s',
            config('app.url_scheme', 'http'),
            $this->centralDomain(),
            $query,
        );
    }

    private function isCentralDomain(string $host): bool
    {
        return in_array($host, config('tenancy.central_domains', []), true);
    }

    private function resolveTenantFromRequest(?string $tenantSlug): ?Tenant
    {
        if ($tenantSlug === null || $tenantSlug === '') {
            return null;
        }

        $tenant = Tenant::query()
            ->whereKey($tenantSlug)
            ->orWhereHas('domains', fn ($query) => $query->where('domain', $tenantSlug))
            ->first();

        if ($tenant !== null && ! tenancy()->initialized) {
            tenancy()->initialize($tenant);
        }

        return $tenant;
    }

    private function resolveTenantSlugFromHost(string $host): ?string
    {
        return Domain::query()
            ->where('domain', $host)
            ->value('tenant_id');
    }

    private function resolveTenantFromHost(string $host): ?Tenant
    {
        $tenantId = $this->resolveTenantSlugFromHost($host);

        if ($tenantId === null) {
            return null;
        }

        return Tenant::query()->whereKey($tenantId)->first();
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
