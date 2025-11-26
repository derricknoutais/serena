<?php

use App\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'app.url' => 'http://saas-template.login',
        'app.url_host' => 'saas-template.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['saas-template.login'],
    ]);
});

function portalTenant(string $id): Tenant
{
    $tenant = Tenant::create([
        'id' => $id,
        'data' => ['name' => ucfirst($id)],
    ]);

    $tenant->createDomain(['domain' => "{$id}.saas-template.test"]);

    return $tenant;
}

test('central login portal renders on central domain', function () {
    $response = $this->get('http://saas-template.login/login');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/CentralLogin'));
});

test('central login portal redirects to central login when tenant exists', function () {
    $tenant = portalTenant('orchid');

    $response = $this->withHeader('X-Inertia', 'true')->post('http://saas-template.login/login/tenant', [
        'tenant' => 'orchid',
    ]);

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', 'http://saas-template.login/login?tenant='.$tenant->id);
});

test('central login portal validates unknown tenant', function () {
    $response = $this->from('http://saas-template.login/login')
        ->post('http://saas-template.login/login/tenant', [
            'tenant' => 'unknown-team',
        ]);

    $response->assertSessionHasErrors('tenant');
});

test('tenant login routes to central login domain with inertia redirect', function () {
    $tenant = portalTenant('daisy');

    $response = $this->withHeader('X-Inertia', 'true')->get('http://'.$tenant->domains()->value('domain').'/login');

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', 'http://saas-template.login/login?tenant='.$tenant->id);
});

test('central login renders tenant login view when tenant is provided', function () {
    $tenant = portalTenant('violet');

    $response = $this->get('http://saas-template.login/login?tenant='.$tenant->id);

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/Login')
        ->where('tenant.slug', $tenant->id)
        ->where('tenant.domain', $tenant->domains()->value('domain')));
});
