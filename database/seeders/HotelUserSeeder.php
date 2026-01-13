<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class HotelUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::query()->with('domains')->get();

        foreach ($tenants as $tenant) {
            $hotels = Hotel::query()->where('tenant_id', $tenant->getKey())->get();

            if ($hotels->isEmpty()) {
                $hotels = collect([
                    Hotel::create([
                        'tenant_id' => $tenant->getKey(),
                        'name' => "{$tenant->name} One",
                        'currency' => 'XAF',
                        'timezone' => 'Africa/Libreville',
                        'check_in_time' => '14:00:00',
                        'check_out_time' => '12:00:00',
                    ]),
                    Hotel::create([
                        'tenant_id' => $tenant->getKey(),
                        'name' => "{$tenant->name} Two",
                        'currency' => 'XAF',
                        'timezone' => 'Africa/Libreville',
                        'check_in_time' => '14:00:00',
                        'check_out_time' => '12:00:00',
                    ]),
                ]);
            }

            $users = User::query()
                ->where('tenant_id', $tenant->getKey())
                ->get();

            foreach ($users as $user) {
                if ($user->hasRole('owner')) {
                    $user->hotels()->syncWithoutDetaching($hotels->pluck('id'));
                    $user->forceFill(['active_hotel_id' => $hotels->first()->id])->save();

                    continue;
                }

                $assignedHotels = $hotels->take(1);
                $user->hotels()->syncWithoutDetaching($assignedHotels->pluck('id'));
                $user->forceFill(['active_hotel_id' => $assignedHotels->first()->id])->save();
            }
        }
    }
}
