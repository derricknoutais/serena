<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\MaintenanceType;
use Illuminate\Database\Seeder;

class MaintenanceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            'Plomberie',
            'Électricité',
            'Froid/Clim',
            'Serrurerie',
            'Internet/TV',
            'Mobilier',
            'Sanitaire',
            'Autre',
        ];

        Hotel::query()
            ->select(['id', 'tenant_id'])
            ->get()
            ->each(function (Hotel $hotel) use ($defaults): void {
                foreach ($defaults as $name) {
                    MaintenanceType::query()->firstOrCreate([
                        'tenant_id' => $hotel->tenant_id,
                        'hotel_id' => $hotel->id,
                        'name' => $name,
                    ], [
                        'is_active' => true,
                    ]);
                }
            });
    }
}
