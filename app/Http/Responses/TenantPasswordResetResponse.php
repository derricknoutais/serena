<?php

namespace App\Http\Responses;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Stancl\Tenancy\Database\Models\Domain;

class TenantPasswordResetResponse implements PasswordResetResponse
{
    public function toResponse($request)
    {
        $tenantDomain = $this->resolveTenantDomain($request, $request->user()?->tenant_id);

        if ($tenantDomain === null) {
            return redirect()->route('login')->with('status', trans($request->status));
        }

        return redirect()->away(sprintf('%s://%s/login', config('app.url_scheme', 'http'), $tenantDomain))
            ->with('status', trans($request->status));
    }

    private function resolveTenantDomain(Request $request, ?string $tenantId): ?string
    {
        $resolvedTenantId = $tenantId ?? Domain::where('domain', $request->getHost())->value('tenant_id');

        if ($resolvedTenantId === null) {
            return null;
        }

        $domain = Tenant::query()->whereKey($resolvedTenantId)->first()?->domains()->value('domain');

        if ($domain !== null) {
            return $domain;
        }

        return sprintf('%s.%s', $resolvedTenantId, config('app.url_host') ?? $request->getHost());
    }
}
