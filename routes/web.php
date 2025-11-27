<?php

use App\Http\Controllers\Activity\ActivityController;
use App\Http\Controllers\Auth\CheckEmailAvailabilityController;
use App\Http\Controllers\Auth\CheckTenantSlugController;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\Invitations\InvitationController;
use App\Http\Controllers\Users\UpdateUserRoleController;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

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

    Route::post('/login/tenant', function (Request $request) {
        $request->validate([
            'tenant' => ['required', 'string', 'max:255'],
        ]);

        $baseDomain = config('app.url_host', 'saas-template.test');

        $input = trim((string) $request->input('tenant'));
        $host = parse_url(Str::startsWith($input, ['http://', 'https://']) ? $input : 'http://'.$input, PHP_URL_HOST) ?? $input;

        $slug = Str::of($host)
            ->replace('.'.$baseDomain, '')
            ->replace($baseDomain, '')
            ->trim('.')
            ->slug()
            ->toString();

        if ($slug === '') {
            return back()->withErrors(['tenant' => 'Please enter a valid tenant domain or slug.']);
        }

        $tenantId = \Stancl\Tenancy\Database\Models\Domain::query()->where('domain', sprintf('%s.%s', $slug, $baseDomain))->value('tenant_id');

        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if (! $tenant) {
            return back()->withErrors(['tenant' => 'We could not find that tenant.']);
        }

        $target = sprintf('%s://%s/login', config('app.url_scheme', 'http'), $tenant->domains()->value('domain') ?? sprintf('%s.%s', $slug, $baseDomain));

        if ($request->header('X-Inertia')) {
            return Inertia::location($target);
        }

        return redirect()->away($target);
    })->name('tenant.login.redirect');

    Route::get('/register/check-slug', CheckTenantSlugController::class)->name('register.slug.check');

    Route::get('/register/check-email', CheckEmailAvailabilityController::class)->name('register.email.check');
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
        return Inertia::render('Dashboard', [
            'users' => \App\Models\User::query()
                ->orderBy('name')
                ->with(['roles'])
                ->get()
                ->map(
                    fn ($user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->roles->first()?->name,
                    ],
                ),
            'roles' => \Spatie\Permission\Models\Role::query()->orderBy('name')->get()->map(
                fn ($role) => [
                    'name' => $role->name,
                ],
            ),
        ]);
    })
        ->middleware(['auth', 'verified'])
        ->name('dashboard');

    Route::post('/invitations', [InvitationController::class, 'store'])
        ->middleware(['auth', 'verified'])
        ->name('invitations.store');

    Route::get('/invitations/accept', [AcceptInvitationController::class, 'show'])->name('invitations.accept.show');

    Route::post('/invitations/accept', [AcceptInvitationController::class, 'store'])->name('invitations.accept.store');

    Route::patch('/users/{user}/role', UpdateUserRoleController::class)
        ->middleware(['auth', 'verified', 'role:owner|manager|superadmin'])
        ->name('users.role.update');

    Route::get('/activity', [ActivityController::class, 'index'])
        ->middleware(['auth', 'verified'])
        ->name('activity.index');
});

/*
|--------------------------------------------------------------------------
| Shared Route Files
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
