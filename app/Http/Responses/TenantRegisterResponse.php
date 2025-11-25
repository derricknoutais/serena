<?php

namespace App\Http\Responses;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse;

class TenantRegisterResponse implements RegisterResponse
{
    public function toResponse($request): Response
    {
        $this->prepareSessionCookieDomain($request);

        $tenantDomain = $this->resolveTenantDomain($request, $request->user()?->tenant_id);

        if ($tenantDomain === null) {
            return redirect()->intended(config('fortify.home'));
        }

        $destination = sprintf('%s://%s/dashboard', config('app.url_scheme', 'http'), $tenantDomain);

        if ($request->header('X-Inertia')) {
            return Inertia::location($destination);
        }

        return redirect()->intended($destination);
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

        $slug = $this->normalizeSlug((string) ($request->input('tenant_subdomain') ?? $tenantId));

        if ($slug === '') {
            return null;
        }

        $host = config('app.url_host') ?? $request->getHost();

        return sprintf('%s.%s', $slug, $host);
    }

    private function normalizeSlug(string $value): string
    {
        return Str::limit(Str::slug($value), 63, '');
    }

    private function prepareSessionCookieDomain(Request $request): void
    {
        $host = $this->centralHost($request);

        if ($host === null) {
            return;
        }

        config()->set('session.domain', '.'.ltrim($host, '.'));
    }

    private function centralHost(Request $request): ?string
    {
        return config('app.url_host')
            ?? config('tenancy.central_domains.0')
            ?? $request->getHost();
    }
}
