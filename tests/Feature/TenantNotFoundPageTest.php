<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

beforeEach(function () {
    config([
        'app.url' => 'http://saas-template.test',
        'app.url_host' => 'saas-template.test',
        'tenancy.central_domains' => ['saas-template.test'],
    ]);
});

test('unknown tenant domain renders not found page', function () {
    Route::middleware('web')->get('/simulate-tenant-missing', function () {
        throw new TenantCouldNotBeIdentifiedOnDomainException('tenant not found');
    });

    $response = $this->get('http://ghost.saas-template.test/simulate-tenant-missing');

    $response->assertStatus(404)->assertInertia(fn (Assert $page) => $page
        ->component('errors/TenantNotFound')
        ->where('domain', 'ghost.saas-template.test'));
});
