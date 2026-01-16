<?php

use App\Models\DemoRequest;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);
});

it('stores a demo request from the landing page', function (): void {
    $payload = [
        'hotel_name' => 'Hôtel du Centre',
        'name' => 'Nadia Bah',
        'phone' => '+237 690 000 000',
        'city' => 'Douala',
        'email' => 'nadia@example.com',
        'message' => 'Nous voulons une démo rapide.',
        'website' => '',
    ];

    $response = $this->post('http://serena.test/demo-request', $payload);

    $response->assertRedirect();
    $response->assertSessionHas('demoSuccess', true);

    expect(DemoRequest::query()->count())->toBe(1);
});

it('rejects demo requests when the honeypot is filled', function (): void {
    $payload = [
        'hotel_name' => 'Hôtel du Centre',
        'name' => 'Nadia Bah',
        'phone' => '+237 690 000 000',
        'website' => 'spam',
    ];

    $response = $this->post('http://serena.test/demo-request', $payload);

    $response->assertSessionHasErrors('website');
    expect(DemoRequest::query()->count())->toBe(0);
});
