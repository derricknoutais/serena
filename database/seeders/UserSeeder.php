<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guardName = config('auth.defaults.guard', 'web');

        $roleMap = collect([
            'owner',
            'manager',
            'receptionist',
            'housekeeping',
            'accountant',
        ])->mapWithKeys(
            fn (string $role) => [$role => Role::findByName($role, $guardName)],
        );

        /** Tenant demo accounts seeded for quick logins (password: password123). */
        $password = Hash::make('password123');

        Tenant::query()->each(function (Tenant $tenant) use ($password, $roleMap): void {
            $users = [
                'owner' => [
                    'name' => "{$tenant->name} Owner",
                    'email' => "owner+{$tenant->slug}@example.com",
                ],
                'manager' => [
                    'name' => "{$tenant->name} Manager",
                    'email' => "manager+{$tenant->slug}@example.com",
                ],
                'receptionist' => [
                    'name' => "{$tenant->name} Receptionist",
                    'email' => "receptionist+{$tenant->slug}@example.com",
                ],
                'housekeeping' => [
                    'name' => "{$tenant->name} Housekeeping",
                    'email' => "housekeeping+{$tenant->slug}@example.com",
                ],
                'accountant' => [
                    'name' => "{$tenant->name} Accountant",
                    'email' => "accountant+{$tenant->slug}@example.com",
                ],
            ];

            foreach ($users as $role => $userData) {
                $user = User::query()->firstOrCreate(
                    [
                        'email' => $userData['email'],
                    ],
                    [
                        'tenant_id' => $tenant->getKey(),
                        'name' => $userData['name'],
                        'password' => $password,
                        'email_verified_at' => now(),
                    ],
                );

                $user->assignRole($roleMap[$role]);
            }
        });
    }
}
