<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpFoundation\Response;

class InitializeFortifyTenancy
{
    public function __construct(
        private InitializeTenancyByDomain $initializeTenancyByDomain,
        private PreventAccessFromCentralDomains $preventAccessFromCentralDomains,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isCentralDomain($request->getHost())) {
            return $this->initializeForCentralDomain($request, $next);
        }

        return $this->initializeTenancyByDomain->handle($request, function ($request) use ($next) {
            return $this->preventAccessFromCentralDomains->handle($request, $next);
        });
    }

    private function initializeForCentralDomain(Request $request, Closure $next): Response
    {
        $tenantKey = $request->query('tenant') ?? $request->input('tenant');

        if (is_string($tenantKey) && $tenantKey !== '') {
            $tenant = $this->resolveTenant($tenantKey);

            if ($tenant instanceof Tenant) {
                tenancy()->initialize($tenant);
            }
        }

        return $next($request);
    }

    private function resolveTenant(string $tenantKey): ?Tenant
    {
        return \App\Models\Tenant::query()
            ->whereKey($tenantKey)
            ->orWhereHas('domains', fn ($query) => $query->where('domain', $tenantKey))
            ->first();
    }

    private function isCentralDomain(string $host): bool
    {
        return in_array($host, config('tenancy.central_domains', []), true);
    }
}
