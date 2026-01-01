<?php

use App\Models\CashSession;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);
});

test('cash session detail page renders', function (): void {
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Cash Tenant',
        'slug' => 'cash-tenant',
        'plan' => 'standard',
        'contact_email' => 'cash@example.com',
        'data' => [
            'name' => 'Cash Tenant',
            'slug' => 'cash-tenant',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'cash-tenant.serena.test']);

    tenancy()->initialize($tenant);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Cash Hotel',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'manager@cash.test',
        'active_hotel_id' => $hotel->id,
    ]);

    $session = CashSession::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'opened_by_user_id' => $user->id,
        'type' => 'frontdesk',
        'started_at' => now(),
        'starting_amount' => 10000,
        'status' => 'open',
    ]);

    $method = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Espèces',
        'code' => 'CASH',
        'type' => 'cash',
        'is_active' => true,
    ]);

    Payment::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'payment_method_id' => $method->id,
        'amount' => 5000,
        'currency' => 'XAF',
        'paid_at' => now(),
        'cash_session_id' => $session->id,
        'created_by_user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/cash/%s',
        $tenant->domains()->value('domain'),
        $session->id,
    ));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('CashSessions/CashSessionShow')
        ->where('session.id', $session->id)
        ->has('paymentBreakdown')
    );
});
