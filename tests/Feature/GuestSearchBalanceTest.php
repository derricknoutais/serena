<?php

use App\Models\Folio;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

it('returns guest balance in search results', function (): void {
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Guest Balance Tenant',
        'slug' => 'guest-balance',
        'plan' => 'standard',
        'contact_email' => 'guest@balance.test',
        'data' => [
            'name' => 'Guest Balance Tenant',
            'slug' => 'guest-balance',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'guest-balance.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Guest Balance Hotel',
        'currency' => 'XAF',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);

    $guard = config('auth.defaults.guard', 'web');
    Permission::query()->firstOrCreate([
        'name' => 'resources.view',
        'guard_name' => $guard,
    ]);
    $user->givePermissionTo('resources.view');

    $guest = Guest::query()->create([
        'tenant_id' => $tenant->getKey(),
        'first_name' => 'Balance',
        'last_name' => 'Lookup',
        'email' => 'balance.lookup@example.com',
        'phone' => '670000000',
    ]);

    $folio = Folio::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'reservation_id' => null,
        'guest_id' => $guest->id,
        'code' => 'FOL-SEARCH',
        'status' => 'open',
        'is_main' => true,
        'type' => 'stay',
        'origin' => 'manual',
        'currency' => 'XAF',
        'billing_name' => $guest->full_name,
        'opened_at' => now(),
    ]);
    $folio->forceFill(['balance' => 9000])->save();

    $response = $this->actingAs($user)
        ->get('http://guest-balance.serena.test/settings/resources/guests/search?search=Balance');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $guest->id,
            'balance_due' => 9000.0,
        ]);
});
