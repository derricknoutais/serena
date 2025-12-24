<?php

require_once __DIR__.'/../FolioTestHelpers.php';

use App\Models\Hotel;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

function setupPaymentMethodTenant(): array
{
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Tenant Payments',
        'slug' => 'payments-tenant',
        'plan' => 'standard',
    ]);

    $tenant->createDomain(['domain' => 'payments-tenant.serena.test']);

    tenancy()->initialize($tenant);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hotel Payments',
        'code' => 'HPM1',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'address' => 'Main street',
        'city' => 'Douala',
        'country' => 'CM',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'active_hotel_id' => $hotel->id,
        'email' => 'payments-user@example.com',
        'email_verified_at' => now(),
    ]);

    $user->hotels()->attach($hotel);

    $guard = config('auth.defaults.guard', 'web');
    $permissions = [
        'payment_methods.view',
        'payment_methods.create',
        'payment_methods.update',
        'payment_methods.delete',
    ];

    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => $guard,
        ]);
    }

    $user->givePermissionTo($permissions);

    return compact('tenant', 'hotel', 'user');
}

it('stores payment methods with config', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupPaymentMethodTenant();

    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $payload = [
        'name' => 'Carte bancaire',
        'code' => 'CARD',
        'type' => 'card',
        'provider' => 'Stripe',
        'account_number' => 'ACC-100',
        'config' => json_encode(['currency' => 'XAF', 'merchant_id' => 'MID-1']),
        'is_active' => true,
        'is_default' => true,
    ];

    $response = actingAs($user)->post(
        sprintf('http://%s/ressources/payment-methods', tenantDomain($tenant)),
        $payload,
    );

    $response->assertRedirect();

    $method = PaymentMethod::query()
        ->where('tenant_id', $tenant->id)
        ->where('hotel_id', $hotel->id)
        ->where('code', 'CARD')
        ->firstOrFail();

    expect($method->provider)->toBe('Stripe')
        ->and($method->config)->toMatchArray([
            'currency' => 'XAF',
            'merchant_id' => 'MID-1',
        ])
        ->and($method->is_default)->toBeTrue();
});

it('updates payment methods', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupPaymentMethodTenant();

    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $method = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Mobile Money',
        'code' => 'MOMO',
        'type' => 'mobile_money',
        'is_active' => true,
    ]);

    $response = actingAs($user)->put(
        sprintf('http://%s/ressources/payment-methods/%d', tenantDomain($tenant), $method->id),
        [
            'name' => 'Mobile Money',
            'code' => 'MOMO',
            'type' => 'mobile_money',
            'provider' => 'MTN',
            'account_number' => 'ACC-200',
            'config' => json_encode(['shortcode' => '1234']),
            'is_active' => false,
            'is_default' => false,
        ],
    );

    $response->assertRedirect();

    $method->refresh();

    expect($method->provider)->toBe('MTN')
        ->and($method->is_active)->toBeFalse()
        ->and($method->config)->toMatchArray(['shortcode' => '1234']);
});

it('rejects invalid config json', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupPaymentMethodTenant();

    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $response = actingAs($user)->post(
        sprintf('http://%s/ressources/payment-methods', tenantDomain($tenant)),
        [
            'name' => 'Espèces',
            'code' => 'CASH',
            'config' => '{bad-json}',
        ],
    );

    $response->assertSessionHasErrors('config');
});

it('deletes payment methods', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupPaymentMethodTenant();

    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $method = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Espèces',
        'code' => 'CASH',
        'type' => 'cash',
        'is_active' => true,
    ]);

    $response = actingAs($user)->delete(
        sprintf('http://%s/ressources/payment-methods/%d', tenantDomain($tenant), $method->id),
    );

    $response->assertRedirect();

    expect(PaymentMethod::query()->whereKey($method->id)->exists())->toBeFalse();
});
