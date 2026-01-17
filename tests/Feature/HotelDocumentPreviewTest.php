<?php

use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

it('shows an invoice preview without creating data', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Preview Hotel',
        'slug' => 'preview-hotel',
        'plan' => 'standard',
        'contact_email' => 'preview@hotel.test',
        'data' => [
            'name' => 'Preview Hotel',
            'slug' => 'preview-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'preview-hotel.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Preview Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
        'document_settings' => [
            'display_name' => 'Preview Docs',
            'header_text' => 'Header preview',
            'footer_text' => 'Footer preview',
        ],
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('hotels.documents.update');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    $countBefore = Invoice::query()->count();

    $this->actingAs($user)
        ->get('http://preview-hotel.serena.test/settings/resources/hotel/documents/invoice-preview')
        ->assertOk()
        ->assertSee('document-header')
        ->assertSee('document-footer')
        ->assertSee('Preview Docs')
        ->assertSee('Header preview')
        ->assertSee('Footer preview');

    expect(Invoice::query()->count())->toBe($countBefore);
});
