<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralForRegistration
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isRegistrationRoute($request) && ! $this->isCentralDomain($request->getHost())) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }

    private function isRegistrationRoute(Request $request): bool
    {
        return $request->routeIs('register', 'register.store');
    }

    private function isCentralDomain(string $host): bool
    {
        $central = config('tenancy.central_domains', []);

        return in_array($host, $central, true);
    }
}
