<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Auth::loginUsingId(1);

/*
|--------------------------------------------------------------------------
| Central (Landlord) Routes
|--------------------------------------------------------------------------
|
| These routes run on the central domain only (e.g. app.test).
| They do NOT have tenant context.
|
*/

Route::middleware('web')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    })->name('home');
});

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| These routes are accessible only from tenant domains (e.g. hotel1.app.test).
| They are automatically scoped by stancl/tenancy.
|
*/

Route::middleware(['web', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])->group(function () {
    Route::get('/dashboard', function () {
        return 'Tenant Dashboard';
        // return Inertia::render('Dashboard');
    })
        ->middleware(['auth', 'verified'])
        ->name('dashboard');

    // You can place more tenant routes here …
});

/*
|--------------------------------------------------------------------------
| Shared Route Files
|--------------------------------------------------------------------------
*/

require __DIR__ . '/settings.php';
