<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TenantSeeder::class,
            UserSeeder::class,
            HotelUserSeeder::class,
            PaymentMethodSeeder::class,
            ProductCategorySeeder::class,
            ProductSeeder::class,
            RoomTypeSeeder::class,
            RoomSeeder::class,
            OfferSeeder::class,
            OfferRoomTypePriceSeeder::class,
            GuestSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
