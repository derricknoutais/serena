<?php

namespace App\Http\Responses;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse;

class TenantRegisterResponse implements RegisterResponse
{
    public function toResponse($request)
    {
        $tenantDomain = $this->resolveTenantDomain($request, $request->user()?->tenant_id);

        if ($tenantDomain === null) {
            return redirect()->intended(config('fortify.home'));
        }

        $base = sprintf('%s://%s', config('app.url_scheme', 'http'), $tenantDomain);
        $destination = $request->user()?->hasVerifiedEmail()
            ? $base.'/dashboard'
            : $base.route('verification.notice', absolute: false);

        if ($request->header('X-Inertia')) {
            return Inertia::location($destination);
        }

        return redirect()->away($destination);
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

        $slug = $this->normalizeSlug((string) ($request->input('tenant_slug') ?? $tenantId));

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
}
