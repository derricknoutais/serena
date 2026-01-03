<?php

declare(strict_types=1);

use App\Models\Offer;

require_once __DIR__.'/FolioTestHelpers.php';

use function Pest\Laravel\actingAs;

it('accepts offer time preview with local datetime input', function (): void {
    ['tenant' => $tenant, 'hotel' => $hotel, 'user' => $user] = setupReservationEnvironment('offer-preview-local');

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Week-end',
        'kind' => 'weekend',
        'time_rule' => 'weekend_window',
        'time_config' => [
            'checkin' => [
                'allowed_weekdays' => [5, 6, 7],
                'start_time' => '00:00',
            ],
            'checkout' => [
                'time' => '12:00',
                'max_days_after_checkin' => 2,
            ],
        ],
    ]);

    $domain = tenantDomain($tenant);

    $response = actingAs($user)
        ->postJson(sprintf('http://%s/api/offers/%s/time-preview', $domain, $offer->id), [
            'arrival_at' => '2025-01-03T10:00',
        ])
        ->assertSuccessful();

    $payload = $response->json();

    expect($payload['arrival_at'])->toBe('2025-01-03T10:00:00');
    expect($payload['departure_at'])->toBe('2025-01-05T12:00:00');
});
