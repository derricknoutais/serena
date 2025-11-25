<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
            return $next($request);
        }

        return $this->initializeTenancyByDomain->handle($request, function ($request) use ($next) {
            return $this->preventAccessFromCentralDomains->handle($request, $next);
        });
    }

    private function isCentralDomain(string $host): bool
    {
        return in_array($host, config('tenancy.central_domains', []), true);
    }
}
