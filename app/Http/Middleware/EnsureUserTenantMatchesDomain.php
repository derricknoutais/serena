<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserTenantMatchesDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = tenant();

        if ($user !== null && $tenant !== null && (string) $user->tenant_id !== (string) $tenant->getTenantKey()) {
            abort(Response::HTTP_FORBIDDEN, 'Access denied for this tenant.');
        }

        return $next($request);
    }
}
