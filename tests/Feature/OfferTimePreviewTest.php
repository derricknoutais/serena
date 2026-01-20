<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Offer;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

it('returns a validation error when the offer is not valid for the weekday', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupReservationEnvironment('offer-time-preview');

    $user->assignRole('owner');

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Week-end',
        'kind' => 'weekend',
        'time_rule' => 'weekend_window',
        'time_config' => [
            'checkin' => [
                'allowed_weekdays' => [6, 7],
                'start_time' => '14:00',
            ],
            'checkout' => [
                'time' => '12:00',
                'max_days_after_checkin' => 2,
            ],
        ],
        'billing_mode' => 'per_stay',
        'is_active' => true,
    ]);

    $arrivalAt = Carbon::parse('2025-01-20 10:00:00')->toDateTimeString();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->post(
            sprintf('http://%s/api/offers/%s/time-preview', tenantDomain($tenant), $offer->id),
            [
                'arrival_at' => $arrivalAt,
            ],
        )
        ->assertUnprocessable()
        ->assertJsonFragment([
            'offer' => ['Cette offre n’est pas valable pour ce jour de la semaine.'],
        ]);
});
