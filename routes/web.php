<?php

use App\Http\Controllers\Activity\ActivityController;
use App\Http\Controllers\Api\ActivityFeedController;
use App\Http\Controllers\Api\OfferTimeController;
use App\Http\Controllers\Auth\CheckEmailAvailabilityController;
use App\Http\Controllers\Auth\CheckTenantSlugController;
use App\Http\Controllers\Config\ActiveHotelController;
use App\Http\Controllers\Config\HotelConfigController;
use App\Http\Controllers\Config\OfferController;
use App\Http\Controllers\Config\PaymentMethodController;
use App\Http\Controllers\Config\ProductCategoryController;
use App\Http\Controllers\Config\ProductController;
use App\Http\Controllers\Config\RoomController;
use App\Http\Controllers\Config\RoomTypeController;
use App\Http\Controllers\Config\TaxController;
use App\Http\Controllers\Config\UserConfigController;
use App\Http\Controllers\FolioController;
use App\Http\Controllers\Frontdesk\FrontdeskController;
use App\Http\Controllers\Frontdesk\GuestController;
use App\Http\Controllers\Frontdesk\ReservationController;
use App\Http\Controllers\Frontdesk\ReservationStatusController;
use App\Http\Controllers\Frontdesk\RoomBoardController;
use App\Http\Controllers\Frontdesk\RoomBoardWalkInController;
use App\Http\Controllers\Frontdesk\RoomHousekeepingController;
use App\Http\Controllers\Frontdesk\WalkInReservationController;
use App\Http\Controllers\HousekeepingController;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\Invitations\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MaintenanceTicketController;
use App\Http\Controllers\NightAuditController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReservationFolioController;
use App\Http\Controllers\ReservationStayController;
use App\Http\Controllers\Users\UpdateUserRoleController;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| These routes are accessible only from tenant domains (e.g. hotel1.app.test).
| They are automatically scoped by stancl/tenancy.
|
*/
if (app()->environment('local') && ! app()->runningInConsole()) {
    // For local development, we can use a specific tenant ID to avoid creating a new tenant
    // This is useful for testing purposes.
    // config(['tenancy.central_domains' => ['saas-template.test']]);
    // config(['tenancy.database.connection' => 'tenant']);
    Auth::loginUsingId(6);
}
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'ensure_user_tenant_matches_domain',
])->group(function () {
    Route::get('/invitations/accept', [AcceptInvitationController::class, 'show'])->name('invitations.accept.show');

    Route::post('/invitations/accept', [AcceptInvitationController::class, 'store'])->name('invitations.accept.store');

    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', function () {
            /** @var \App\Models\User $user */
            $user = request()->user();

            $activeHotelId = session('active_hotel_id', $user?->active_hotel_id);

            if ($user !== null && $activeHotelId !== null) {
                $belongs = $user->hotels()->where('hotels.id', $activeHotelId)->exists();
                if (! $belongs) {
                    $activeHotelId = null;
                }
            }

            if ($user !== null && $activeHotelId === null) {
                $firstHotel = $user->hotels()->first();
                if ($firstHotel) {
                    $activeHotelId = $firstHotel->id;
                    $user->forceFill(['active_hotel_id' => $activeHotelId])->save();
                    session()->put('active_hotel_id', $activeHotelId);
                }
            }

            $hotel = null;

            if ($activeHotelId !== null) {
                $hotel = \App\Models\Hotel::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('id', $activeHotelId)
                    ->first();
            }

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
                'hotel' => $hotel?->only([
                    'id',
                    'name',
                    'code',
                    'currency',
                    'timezone',
                    'address',
                    'city',
                    'country',
                    'check_in_time',
                    'check_out_time',
                ]),
            ]);
        })
            ->middleware(['auth', 'verified'])
            ->name('dashboard');

        Route::post('/invitations', [InvitationController::class, 'store'])
            ->middleware(['auth', 'verified'])
            ->name('invitations.store');

        Route::get('/resources/hotel', function () {
            return redirect()->route('ressources.hotel.edit');
        });

        Route::patch('/users/{user}/role', UpdateUserRoleController::class)
            ->middleware(['auth', 'verified', 'role:owner|manager|superadmin'])
            ->name('users.role.update');

        Route::middleware('role:owner|manager|receptionist|accountant|superadmin')->group(function () {
            Route::get('/pos', [PosController::class, 'index'])
                ->name('pos.index');
            Route::post('/pos/sales/counter', [PosController::class, 'storeCounterSale'])
                ->name('pos.sales.counter');
            Route::post('/pos/sales/room', [PosController::class, 'storeRoomSale'])
                ->name('pos.sales.room');
        });

        Route::middleware('role:owner|manager|superadmin')->group(function () {
            Route::get('/night-audit', [NightAuditController::class, 'index'])->name('night-audit.index');
            Route::get('/night-audit/pdf', [NightAuditController::class, 'pdf'])->name('night-audit.pdf');
        });

        // Cash Management
        Route::group(['prefix' => 'cash', 'as' => 'cash.'], function () {
            Route::get('/', [\App\Http\Controllers\CashSessionController::class, 'index'])->name('index');
            Route::get('/status', [\App\Http\Controllers\CashSessionController::class, 'status'])->name('status');
            Route::post('/', [\App\Http\Controllers\CashSessionController::class, 'store'])->name('store');
            Route::post('{cashSession}/close', [\App\Http\Controllers\CashSessionController::class, 'close'])->name('close');
            Route::post('{cashSession}/transaction', [\App\Http\Controllers\CashSessionController::class, 'transaction'])->name('transaction');
            Route::post('{cashSession}/validate', [\App\Http\Controllers\CashSessionController::class, 'validateSession'])->name('validate');
        });

        Route::get('/housekeeping', [HousekeepingController::class, 'index'])
            ->name('housekeeping.index');
        Route::get('/hk/rooms/{room}', [HousekeepingController::class, 'show'])
            ->name('housekeeping.rooms.show');
        Route::patch('/hk/rooms/{room}/status', [HousekeepingController::class, 'updateStatus'])
            ->name('housekeeping.rooms.update');

        Route::get('/rooms/board', [RoomBoardController::class, 'index'])
            ->name('rooms.board');

        Route::patch('/frontdesk/rooms/{room}/hk-status', [RoomHousekeepingController::class, 'updateStatus'])
            ->middleware('idempotency')
            ->name('frontdesk.rooms.hk-status');
        Route::get('/maintenance', [MaintenanceTicketController::class, 'index'])
            ->middleware('role:owner|manager|maintenance|superadmin')
            ->name('maintenance.index');
        Route::post('/maintenance-tickets', [MaintenanceTicketController::class, 'store'])
            ->name('maintenance-tickets.store');
        Route::patch('/maintenance-tickets/{maintenanceTicket}', [MaintenanceTicketController::class, 'update'])
            ->name('maintenance-tickets.update');

        Route::get('/reservations/walk-in/create', [WalkInReservationController::class, 'create'])
            ->name('reservations.walk_in.create');

        Route::post('/reservations/walk-in', [WalkInReservationController::class, 'store'])
            ->name('reservations.walk_in.store');

        Route::middleware('role:owner|manager|superadmin')->group(function () {
            Route::get('/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics.index');
            Route::get('/analytics/summary', [\App\Http\Controllers\AnalyticsController::class, 'summary'])->name('analytics.summary');
            Route::get('/analytics/trends', [\App\Http\Controllers\AnalyticsController::class, 'trends'])->name('analytics.trends');
            Route::get('/analytics/payments', [\App\Http\Controllers\AnalyticsController::class, 'payments'])->name('analytics.payments');
            Route::get('/analytics/top-products', [\App\Http\Controllers\AnalyticsController::class, 'topProducts'])->name('analytics.top_products');
        });

        Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::patch('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])
            ->name('notifications.read_all');
        Route::patch('/notifications/{notification}', [\App\Http\Controllers\NotificationController::class, 'markRead'])
            ->name('notifications.read');

        Route::get('/guests', [GuestController::class, 'index'])->name('guests.index');
        Route::get('/guests/create', [GuestController::class, 'create'])->name('guests.create');
        Route::post('/guests', [GuestController::class, 'store'])->name('guests.store');
        Route::get('/guests/search', [GuestController::class, 'search'])->name('guests.search');
        Route::get('/guests/{guest}', [GuestController::class, 'show'])->name('guests.show');
        Route::get('/guests/{guest}/edit', [GuestController::class, 'edit'])->name('guests.edit');
        Route::put('/guests/{guest}', [GuestController::class, 'update'])->name('guests.update');
        Route::delete('/guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');

        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/frontdesk/dashboard', [FrontdeskController::class, 'dashboard'])->name('frontdesk.dashboard');
        Route::get('/frontdesk/forecast', [FrontdeskController::class, 'forecast'])
            ->middleware('role:owner|manager|superadmin')
            ->name('frontdesk.forecast');
        Route::post('/frontdesk/room-board/walk-in', [RoomBoardWalkInController::class, 'store'])
            ->middleware('idempotency')
            ->name('frontdesk.room_board.walk_in');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
        Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
        Route::patch('/reservations/{reservation}/status', [ReservationStatusController::class, 'update'])
            ->middleware('idempotency')
            ->name('reservations.status');
        Route::patch('/reservations/{reservation}/stay/dates', [ReservationStayController::class, 'updateDates'])
            ->middleware('idempotency')
            ->name('reservations.stay.dates');
        Route::patch('/reservations/{reservation}/stay/room', [ReservationStayController::class, 'changeRoom'])
            ->name('reservations.stay.room');

        Route::prefix('frontdesk')->group(function () {
            Route::get('/arrivals', [FrontdeskController::class, 'arrivals'])->name('frontdesk.arrivals');
            Route::get('/departures', [FrontdeskController::class, 'departures'])->name('frontdesk.departures');
            Route::get('/in-house', [FrontdeskController::class, 'inHouse'])->name('frontdesk.in_house');
        });

        Route::get('/reservations/{reservation}/folio', [ReservationFolioController::class, 'show'])
            ->name('reservations.folio.show');

        Route::post('/frontdesk/reservations/from-offer', [\App\Http\Controllers\Api\ReservationFromOfferController::class, 'store'])
            ->name('frontdesk.reservations.from_offer');
        Route::post('/api/offers/{offer}/time-preview', [OfferTimeController::class, 'preview'])
            ->name('api.offers.time_preview');
        Route::post('/reservations/{reservation}/stay-adjustments/preview', [ReservationStatusController::class, 'preview'])
            ->name('reservations.stay.preview');
        Route::get('/reservations/{reservation}/activity', [ActivityFeedController::class, 'reservation'])
            ->name('reservations.activity');
        Route::get('/rooms/{room}/activity', [ActivityFeedController::class, 'room'])
            ->name('rooms.activity');
        Route::get('/audit/activity', [ActivityFeedController::class, 'index'])
            ->name('audit.activity');

        Route::get('/folios/{folio}', [FolioController::class, 'show'])->name('folios.show');
        Route::post('/folios/{folio}/items', [FolioController::class, 'storeItem'])->name('folios.items.store');
        Route::post('/folios/{folio}/payments', [FolioController::class, 'storePayment'])->name('folios.payments.store');
        Route::delete('/folios/{folio}/payments/{payment}', [FolioController::class, 'destroyPayment'])->name('folios.payments.destroy');
        Route::post('/folios/{folio}/invoices', [InvoiceController::class, 'storeFromFolio'])->name('folios.invoices.store');
        Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    });

    Route::get('/activity', [ActivityController::class, 'index'])
        ->middleware(['auth', 'verified'])
        ->name('activity.index');

    Route::prefix('ressources')
        ->name('ressources.')
        ->middleware(['auth'])
        ->group(function () {
            Route::post('/active-hotel', ActiveHotelController::class)->name('active-hotel');
            Route::get('/hotel', [HotelConfigController::class, 'edit'])->name('hotel.edit');
            Route::put('/hotel', [HotelConfigController::class, 'update'])->name('hotel.update');

            Route::resource('room-types', RoomTypeController::class);
            Route::post('room-types/{roomType}/prices', [RoomTypeController::class, 'storePrice'])->name('room-types.prices.store');
            Route::resource('rooms', RoomController::class)->except(['show']);
            Route::resource('offers', OfferController::class)->except(['show']);
            Route::resource('taxes', TaxController::class)->except(['show']);
            Route::resource('payment-methods', PaymentMethodController::class)->except(['show']);
            Route::resource('products', ProductController::class)->except(['show']);
            Route::resource('product-categories', ProductCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('users', UserConfigController::class)->except(['show']);
        });
});

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
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Landing/Index', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    })->name('home');

    Route::get('/login/tenant', function () {
        return Inertia::render('Auth/TenantLogin', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    })->name('tenant.login.form');

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

        $user = $request->user();

        if ($user && (string) $user->tenant_id !== (string) $tenant->id) {
            return back()
                ->withErrors(['tenant' => 'You are not allowed to access this tenant.'])
                ->setStatusCode(403);
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
| Shared Route Files
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
