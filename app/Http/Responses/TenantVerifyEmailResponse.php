<?php

namespace App\Http\Responses;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse;

class TenantVerifyEmailResponse implements VerifyEmailResponse
{
    public function toResponse($request)
    {
        $tenantDomain = $this->resolveTenantDomain($request, $request->user()?->tenant_id);

        if ($tenantDomain === null) {
            return redirect()->intended(config('fortify.home'));
        }

        return redirect()->intended(sprintf('%s://%s/dashboard', config('app.url_scheme', 'http'), $tenantDomain));
    }

    private function resolveTenantDomain(Request $request, ?string $tenantId): ?string
    {
        if ($tenantId === null) {
            return null;
        }

        $domain = Tenant::query()->whereKey($tenantId)->first()?->domains()->value('domain');

        if ($domain !== null) {
            return $domain;
        }

        return sprintf('%s.%s', $tenantId, config('app.url_host') ?? $request->getHost());
    }
}
