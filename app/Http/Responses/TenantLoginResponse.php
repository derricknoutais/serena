<?php

namespace App\Http\Responses;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Stancl\Tenancy\Database\Models\Domain;

class TenantLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $tenantId = $this->resolveTenantId($request);
        $targetDomain = $this->resolveTenantDomain($tenantId, $request->getHost());
        $targetPath = $this->resolveTargetPath($request);

        $targetUrl = sprintf(
            '%s://%s%s',
            config('app.url_scheme', 'http'),
            $targetDomain,
            $targetPath,
        );

        return redirect()->away($targetUrl);
    }

    private function resolveTenantId(Request $request): ?string
    {
        if (tenancy()->initialized) {
            return tenant()->getTenantKey();
        }

        $fromDomain = Domain::query()
            ->where('domain', $request->getHost())
            ->value('tenant_id');

        return $fromDomain ?: $request->user()?->tenant_id;
    }

    private function resolveTenantDomain(?string $tenantId, string $fallbackHost): string
    {
        if ($tenantId === null) {
            return $fallbackHost;
        }

        $domain = Tenant::query()
            ->whereKey($tenantId)
            ->first()
            ?->domains()
            ->value('domain');

        if ($domain !== null) {
            return $domain;
        }

        return sprintf('%s.%s', $tenantId, config('app.url_host') ?? $fallbackHost);
    }

    private function resolveTargetPath(Request $request): string
    {
        $user = $request->user();

        if ($user !== null && $user->hasRole('housekeeping')) {
            return '/housekeeping';
        }

        return config('fortify.home', '/dashboard');
    }
}
