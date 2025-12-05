<?php

use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);
});

it('seeds minimal accommodation data for each hotel idempotently', function (): void {
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Seeder Tenant',
        'slug' => 'seeder-tenant',
        'plan' => 'standard',
    ]);

    $tenant->createDomain(['domain' => 'seeder-tenant.serena.test']);

    tenancy()->initialize($tenant);

    $hotelA = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hotel A',
        'code' => 'HOTA',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'address' => 'Main street',
        'city' => 'Douala',
        'country' => 'CM',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $hotelB = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hotel B',
        'code' => 'HOTB',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'address' => 'Second street',
        'city' => 'Douala',
        'country' => 'CM',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $this->artisan('db:seed', ['--class' => \Database\Seeders\RoomTypeSeeder::class])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => \Database\Seeders\RoomSeeder::class])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => \Database\Seeders\OfferSeeder::class])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => \Database\Seeders\OfferRoomTypePriceSeeder::class])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => \Database\Seeders\GuestSeeder::class])->assertExitCode(0);

    expect(RoomType::query()->where('hotel_id', $hotelA->id)->count())->toBeGreaterThanOrEqual(1);
    expect(RoomType::query()->where('hotel_id', $hotelB->id)->count())->toBeGreaterThanOrEqual(1);

    expect(Room::query()->where('hotel_id', $hotelA->id)->count())->toBeGreaterThan(0);
    expect(Room::query()->where('hotel_id', $hotelB->id)->count())->toBeGreaterThan(0);

    expect(Offer::query()->where('hotel_id', $hotelA->id)->count())->toBeGreaterThanOrEqual(4);
    expect(Offer::query()->where('hotel_id', $hotelB->id)->count())->toBeGreaterThanOrEqual(4);

    $pricesA = OfferRoomTypePrice::query()->where('hotel_id', $hotelA->id)->count();
    $pricesB = OfferRoomTypePrice::query()->where('hotel_id', $hotelB->id)->count();

    expect($pricesA)->toBeGreaterThan(0);
    expect($pricesB)->toBeGreaterThan(0);

    $guestsTenantCount = Guest::query()->where('tenant_id', $tenant->id)->count();
    expect($guestsTenantCount)->toBeGreaterThanOrEqual(5);

    $roomTypesCountA = RoomType::query()->where('hotel_id', $hotelA->id)->count();
    $offersCountA = Offer::query()->where('hotel_id', $hotelA->id)->count();
    $guestsCountTenantBefore = Guest::query()->where('tenant_id', $tenant->id)->count();

    $this->artisan('db:seed', ['--class' => \Database\Seeders\RoomTypeSeeder::class])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => \Database\Seeders\RoomSeeder::class])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => \Database\Seeders\OfferSeeder::class])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => \Database\Seeders\OfferRoomTypePriceSeeder::class])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => \Database\Seeders\GuestSeeder::class])->assertExitCode(0);

    expect(RoomType::query()->where('hotel_id', $hotelA->id)->count())->toBe($roomTypesCountA);
    expect(Offer::query()->where('hotel_id', $hotelA->id)->count())->toBe($offersCountA);
    expect(Guest::query()->where('tenant_id', $tenant->id)->count())->toBe($guestsCountTenantBefore);
})
    ->group('seeders');
