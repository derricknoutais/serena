<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionsTeam
{
    public function __construct(private PermissionRegistrar $permissions) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $teamId = tenancy()->initialized ? tenant()->getTenantKey() : null;

        $this->permissions->setPermissionsTeamId($teamId);

        return $next($request);
    }
}
