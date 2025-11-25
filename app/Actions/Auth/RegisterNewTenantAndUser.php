<?php

namespace App\Actions\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Tenancy;

class RegisterNewTenantAndUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private Tenancy $tenancy) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $businessName = trim((string) ($input['business_name'] ?? ''));
        $tenantSlug = $this->determineTenantSlug($input, $businessName);

        $validator = Validator::make($input, [
            'business_name' => ['required', 'string', 'max:255'],
            'tenant_slug' => ['nullable', 'string', 'max:63', 'alpha_dash'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->where(fn ($query) => $query->where('tenant_id', $tenantSlug)),
            ],
            'password' => $this->passwordRules(),
        ]);

        $validator->after(function () use ($tenantSlug, $validator): void {
            if ($tenantSlug === '') {
                $validator->errors()->add('tenant_slug', 'Please choose a valid subdomain.');

                return;
            }

            if (Tenant::whereKey($tenantSlug)->exists()) {
                $validator->errors()->add('tenant_slug', 'This subdomain is already taken.');
            }

            if (Domain::where('domain', $this->buildTenantDomain($tenantSlug))->exists()) {
                $validator->errors()->add('tenant_slug', 'This subdomain is already taken.');
            }
        });

        $validator->validate();

        $tenant = Tenant::create([
            'id' => $tenantSlug,
            'data' => [
                'name' => $businessName,
                'contact_email' => $input['email'],
                'plan' => 'standard',
            ],
        ]);

        $tenant->createDomain([
            'domain' => $this->buildTenantDomain($tenantSlug),
        ]);

        $this->tenancy->initialize($tenant);

        try {
            return User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
        } finally {
            $this->tenancy->end();
        }
    }

    private function determineTenantSlug(array $input, string $businessName): string
    {
        $value = $input['tenant_slug'] ?? $businessName;
        $slug = Str::slug((string) $value);

        return Str::limit($slug, 63, '');
    }

    private function buildTenantDomain(string $tenantSlug): string
    {
        return sprintf('%s.%s', $tenantSlug, config('app.url_host', 'saas-template.test'));
    }
}
