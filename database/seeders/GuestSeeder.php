<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\Hotel;
use Illuminate\Database\Seeder;

class GuestSeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $tenantId = $hotel->tenant_id;

            $guestList = [
                [
                    'first_name' => 'Jean',
                    'last_name' => 'Dupont',
                    'email' => 'jean.dupont@example.com',
                    'phone' => '+237650000001',
                    'address' => 'Yaoundé',
                    'city' => 'Yaoundé',
                    'country' => 'Cameroun',
                    'document_type' => 'CNI',
                    'document_number' => 'CNI123456',
                ],
                [
                    'first_name' => 'Marie',
                    'last_name' => 'Claire',
                    'email' => 'marie.claire@example.com',
                    'phone' => '+237650000002',
                    'address' => 'Douala',
                    'city' => 'Douala',
                    'country' => 'Cameroun',
                    'document_type' => 'Passeport',
                    'document_number' => 'P123456789',
                ],
                [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'email' => 'john.smith@example.com',
                    'phone' => '+237650000003',
                    'address' => 'London',
                    'city' => 'Londres',
                    'country' => 'Royaume-Uni',
                    'document_type' => 'Passeport',
                    'document_number' => 'UK987654321',
                ],
                [
                    'first_name' => 'Fatou',
                    'last_name' => 'Diop',
                    'email' => 'fatou.diop@example.com',
                    'phone' => '+221770000004',
                    'address' => 'Dakar',
                    'city' => 'Dakar',
                    'country' => 'Sénégal',
                    'document_type' => 'CNI',
                    'document_number' => 'SN445566',
                ],
                [
                    'first_name' => 'Carlos',
                    'last_name' => 'Mendes',
                    'email' => 'carlos.mendes@example.com',
                    'phone' => '+244940000005',
                    'address' => 'Luanda',
                    'city' => 'Luanda',
                    'country' => 'Angola',
                    'document_type' => 'Passeport',
                    'document_number' => 'AO99887766',
                ],
            ];

            foreach ($guestList as $guest) {
                Guest::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'email' => $guest['email'],
                    ],
                    [
                        'first_name' => $guest['first_name'],
                        'last_name' => $guest['last_name'],
                        'phone' => $guest['phone'],
                        'address' => $guest['address'],
                        'city' => $guest['city'],
                        'country' => $guest['country'],
                        'document_type' => $guest['document_type'],
                        'document_number' => $guest['document_number'],
                        'notes' => null,
                    ],
                );
            }
        });
    }
}
