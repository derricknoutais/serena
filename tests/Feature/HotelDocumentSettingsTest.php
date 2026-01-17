<?php

use App\Models\Hotel;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('updates document settings for the active hotel', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Docs Hotel',
        'slug' => 'docs-hotel',
        'plan' => 'standard',
        'contact_email' => 'docs@hotel.test',
        'data' => [
            'name' => 'Docs Hotel',
            'slug' => 'docs-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'docs-hotel.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Docs Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('hotels.documents.update');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    $payload = [
        'name' => 'Docs Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
        'address' => 'Rue 1',
        'city' => 'Douala',
        'country' => 'CM',
        'early_policy' => 'free',
        'early_fee_type' => 'flat',
        'early_fee_value' => 0,
        'early_cutoff_time' => '',
        'late_policy' => 'free',
        'late_fee_type' => 'flat',
        'late_fee_value' => 0,
        'late_max_time' => '',
        'document_display_name' => 'Hotel Docs',
        'document_contact_address' => 'BP 123',
        'document_contact_phone' => '+237600000000',
        'document_contact_email' => 'contact@docs.test',
        'document_legal_nif' => 'NIF-001',
        'document_legal_rccm' => 'RCCM-001',
        'document_header_text' => 'Header texte',
        'document_footer_text' => 'Footer texte',
    ];

    $this->actingAs($user)
        ->put('http://docs-hotel.serena.test/settings/resources/hotel', $payload)
        ->assertRedirect();

    $hotel->refresh();

    expect($hotel->document_settings['display_name'])->toBe('Hotel Docs')
        ->and($hotel->document_settings['contact']['phone'])->toBe('+237600000000')
        ->and($hotel->document_settings['legal']['nif'])->toBe('NIF-001')
        ->and($hotel->document_settings['header_text'])->toBe('Header texte')
        ->and($hotel->document_settings['footer_text'])->toBe('Footer texte');
});

it('uploads and deletes a document logo', function (): void {
    Storage::fake('public');

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Logo Hotel',
        'slug' => 'logo-hotel',
        'plan' => 'standard',
        'contact_email' => 'logo@hotel.test',
        'data' => [
            'name' => 'Logo Hotel',
            'slug' => 'logo-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'logo-hotel.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Logo Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    $file = UploadedFile::fake()->image('logo.png', 200, 200);

    $response = $this->actingAs($user)->post(
        'http://logo-hotel.serena.test/settings/resources/hotel/documents/logo',
        ['logo' => $file],
    );

    $response->assertOk();

    $hotel->refresh();
    $path = $hotel->document_settings['logo_path'] ?? null;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    $this->actingAs($user)
        ->delete('http://logo-hotel.serena.test/settings/resources/hotel/documents/logo')
        ->assertOk();

    $hotel->refresh();
    expect($hotel->document_settings['logo_path'])->toBeNull();
});

it('rejects oversized document logos', function (): void {
    Storage::fake('public');

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Large Logo Hotel',
        'slug' => 'large-logo-hotel',
        'plan' => 'standard',
        'contact_email' => 'large@hotel.test',
        'data' => [
            'name' => 'Large Logo Hotel',
            'slug' => 'large-logo-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'large-logo-hotel.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Large Logo Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('hotels.documents.update');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    $file = UploadedFile::fake()->image('logo.png', 10, 10)->size(13000);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->post('http://large-logo-hotel.serena.test/settings/resources/hotel/documents/logo', ['logo' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('logo');
});
