<?php

namespace App\Providers;

use App\Actions\Auth\RegisterNewTenantAndUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\TenantRegisterResponse;
use App\Http\Responses\TenantVerifyEmailResponse;
use App\Models\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

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
                $tenant = $this->resolveTenantFromRequest($request->query('tenant'));

                if ($tenant !== null) {
                    return Inertia::render('auth/Login', [
                        'canResetPassword' => Features::enabled(Features::resetPasswords()),
                        'canRegister' => Features::enabled(Features::registration()),
                        'status' => $request->session()->get('status'),
                        'tenant' => [
                            'name' => $tenant->name ?? $tenant->id,
                            'domain' => $tenant->domains()->value('domain'),
                            'slug' => $tenant->id,
                        ],
                        'centralLoginUrl' => $this->centralLoginUrl(),
                    ]);
                }

                return Inertia::render('auth/CentralLogin', [
                    'centralDomain' => config('app.url_host'),
                    'tenant' => $request->query('tenant'),
                ]);
            }

            return $this->redirectToCentralLogin($request);
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

    private function redirectToCentralLogin(Request $request): Response
    {
        $tenantSlug = $this->resolveTenantSlugFromHost($request->getHost());
        $target = $this->centralLoginUrl($tenantSlug);

        if ($request->header('X-Inertia')) {
            return Inertia::location($target);
        }

        return redirect()->away($target);
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
