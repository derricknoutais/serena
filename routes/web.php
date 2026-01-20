<?php

use App\Http\Controllers\Activity\ActivityController;
use App\Http\Controllers\Activity\ActivityJournalController;
use App\Http\Controllers\Api\ActivityFeedController;
use App\Http\Controllers\Api\OfferTimeController;
use App\Http\Controllers\Auth\BadgeLoginController;
use App\Http\Controllers\Auth\CheckEmailAvailabilityController;
use App\Http\Controllers\Auth\CheckTenantSlugController;
use App\Http\Controllers\Auth\SwitchUserController;
use App\Http\Controllers\BarOrderController;
use App\Http\Controllers\BarTableController;
use App\Http\Controllers\Config\ActiveHotelController;
use App\Http\Controllers\Config\BarTableConfigController;
use App\Http\Controllers\Config\HotelConfigController;
use App\Http\Controllers\Config\HotelDocumentLogoController;
use App\Http\Controllers\Config\HotelDocumentPreviewController;
use App\Http\Controllers\Config\HousekeepingChecklistController;
use App\Http\Controllers\Config\HousekeepingChecklistItemController;
use App\Http\Controllers\Config\MaintenanceTypeController;
use App\Http\Controllers\Config\OfferController;
use App\Http\Controllers\Config\PaymentMethodController;
use App\Http\Controllers\Config\ProductCategoryController;
use App\Http\Controllers\Config\ProductController;
use App\Http\Controllers\Config\RoomController;
use App\Http\Controllers\Config\RoomTypeController;
use App\Http\Controllers\Config\StockItemController;
use App\Http\Controllers\Config\StorageLocationController;
use App\Http\Controllers\Config\TaxController;
use App\Http\Controllers\Config\TechnicianController;
use App\Http\Controllers\Config\UserConfigController;
use App\Http\Controllers\DemoRequestController;
use App\Http\Controllers\FolioController;
use App\Http\Controllers\Frontdesk\FolioAdjustmentController;
use App\Http\Controllers\Frontdesk\FrontdeskController;
use App\Http\Controllers\Frontdesk\GuestController;
use App\Http\Controllers\Frontdesk\ReservationController;
use App\Http\Controllers\Frontdesk\ReservationCorrectionController;
use App\Http\Controllers\Frontdesk\ReservationDetailsController;
use App\Http\Controllers\Frontdesk\ReservationStatusController;
use App\Http\Controllers\Frontdesk\ReservationTimelineController;
use App\Http\Controllers\Frontdesk\RoomBoardController;
use App\Http\Controllers\Frontdesk\RoomBoardWalkInController;
use App\Http\Controllers\Frontdesk\RoomHousekeepingController;
use App\Http\Controllers\Frontdesk\WalkInReservationController;
use App\Http\Controllers\HousekeepingController;
use App\Http\Controllers\HousekeepingReportController;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\Invitations\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MaintenanceInterventionController;
use App\Http\Controllers\MaintenanceInterventionCostController;
use App\Http\Controllers\MaintenanceTicketController;
use App\Http\Controllers\NightAuditController;
use App\Http\Controllers\PaymentAdjustmentController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReservationFolioController;
use App\Http\Controllers\ReservationStayController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockInventoryController;
use App\Http\Controllers\StockLocationController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockPurchaseController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\Users\UpdateUserHotelsController;
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
    Auth::loginUsingId(5);
}
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'ensure_user_tenant_matches_domain',
])->group(function () {
    Route::get('/invitations/accept', [AcceptInvitationController::class, 'show'])->name('invitations.accept.show');

    Route::post('/invitations/accept', [AcceptInvitationController::class, 'store'])->name('invitations.accept.store');

    Route::post('/login/badge', [BadgeLoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.badge');

    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])
        ->name('push.subscribe');

    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])
        ->name('push.unsubscribe');

    Route::post('/push/test', [PushSubscriptionController::class, 'test'])
        ->middleware(['auth', 'role:owner|manager|superadmin'])
        ->name('push.test');

    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
            ->middleware(['auth', 'verified'])
            ->name('dashboard');

        Route::get('/switch-user', [SwitchUserController::class, 'show'])
            ->name('switch-user.show');

        Route::post('/switch-user', [SwitchUserController::class, 'store'])
            ->name('switch-user.store');

        Route::post('/switch-user/badge', [SwitchUserController::class, 'storeBadge'])
            ->middleware('throttle:10,1')
            ->name('switch-user.badge');

        Route::post('/invitations', [InvitationController::class, 'store'])
            ->middleware(['auth', 'verified'])
            ->name('invitations.store');

        Route::get('/resources/hotel', function () {
            return redirect()->route('ressources.hotel.edit');
        });
        Route::get('/resources/housekeeping-checklists', function () {
            return redirect()->route('ressources.housekeeping-checklists.index');
        });
        Route::get('/resources/guests', function () {
            return redirect()->route('ressources.guests.index');
        });

        Route::patch('/users/{user}/role', UpdateUserRoleController::class)
            ->middleware(['auth', 'verified', 'role:owner|manager|superadmin'])
            ->name('users.role.update');

        Route::patch('/users/{user}/hotels', UpdateUserHotelsController::class)
            ->middleware(['auth', 'verified', 'role:owner|manager|superadmin'])
            ->name('users.hotels.update');

        Route::middleware('can:pos.view')->group(function () {
            Route::get('/pos', [PosController::class, 'index'])
                ->name('pos.index');
            Route::post('/pos/sales/counter', [PosController::class, 'storeCounterSale'])
                ->middleware('can:pos.create')
                ->name('pos.sales.counter');
            Route::post('/pos/sales/room', [PosController::class, 'storeRoomSale'])
                ->middleware('can:pos.create')
                ->name('pos.sales.room');

            Route::get('/bar/tables', [BarTableController::class, 'index'])
                ->name('bar.tables.index');
            Route::post('/bar/tables', [BarTableController::class, 'store'])
                ->middleware('can:pos.tables.manage')
                ->name('bar.tables.store');
            Route::put('/bar/tables/{barTable}', [BarTableController::class, 'update'])
                ->middleware('can:pos.tables.manage')
                ->name('bar.tables.update');
            Route::post('/bar/orders/open-for-table', [BarOrderController::class, 'openForTable'])
                ->middleware('can:pos.create')
                ->name('bar.orders.open_for_table');
            Route::patch('/bar/orders/{barOrder}/move-table', [BarOrderController::class, 'moveTable'])
                ->middleware('can:pos.tables.manage')
                ->name('bar.orders.move_table');
            Route::post('/bar/orders/{barOrder}/void', [BarOrderController::class, 'void'])
                ->middleware('can:pos.stock.return')
                ->name('bar.orders.void');
        });

        Route::get('/night-audit', [NightAuditController::class, 'index'])
            ->middleware('can:night_audit.view')
            ->name('night-audit.index');
        Route::get('/night-audit/pdf', [NightAuditController::class, 'pdf'])
            ->middleware('can:night_audit.export')
            ->name('night-audit.pdf');
        Route::post('/night-audit/{business_date}/close', [NightAuditController::class, 'close'])
            ->middleware('can:night_audit.close')
            ->name('night-audit.close');
        Route::post('/night-audit/{business_date}/reopen', [NightAuditController::class, 'reopen'])
            ->middleware('can:night_audit.reopen')
            ->name('night-audit.reopen');

        // Cash Management
        Route::group(['prefix' => 'cash', 'as' => 'cash.', 'middleware' => 'can:cash_sessions.view'], function () {
            Route::get('/', [\App\Http\Controllers\CashSessionController::class, 'index'])->name('index');
            Route::get('/status', [\App\Http\Controllers\CashSessionController::class, 'status'])->name('status');
            Route::get('{cashSession}', [\App\Http\Controllers\CashSessionController::class, 'show'])->name('show');
            Route::post('/', [\App\Http\Controllers\CashSessionController::class, 'store'])->name('store');
            Route::post('{cashSession}/close', [\App\Http\Controllers\CashSessionController::class, 'close'])->name('close');
            Route::post('{cashSession}/transaction', [\App\Http\Controllers\CashSessionController::class, 'transaction'])->name('transaction');
            Route::post('{cashSession}/validate', [\App\Http\Controllers\CashSessionController::class, 'validateSession'])->name('validate');
        });

        Route::middleware('can:housekeeping.view')->group(function () {
            Route::get('/housekeeping', [HousekeepingController::class, 'index'])
                ->name('housekeeping.index');
            Route::get('/housekeeping/reports', [HousekeepingReportController::class, 'index'])
                ->name('housekeeping.reports');
            Route::get('/hk/rooms/{room}', [HousekeepingController::class, 'show'])
                ->name('housekeeping.rooms.show');
            Route::patch('/hk/rooms/{room}/status', [HousekeepingController::class, 'updateStatus'])
                ->name('housekeeping.rooms.update');
            Route::post('/hk/rooms/{room}/tasks/start', [HousekeepingController::class, 'startTask'])
                ->name('housekeeping.rooms.tasks.start');
            Route::post('/hk/rooms/{room}/tasks/join', [HousekeepingController::class, 'joinTask'])
                ->name('housekeeping.rooms.tasks.join');
            Route::post('/hk/rooms/{room}/tasks/finish', [HousekeepingController::class, 'finishTask'])
                ->name('housekeeping.rooms.tasks.finish');
            Route::post('/hk/rooms/{room}/inspections/start', [HousekeepingController::class, 'startInspection'])
                ->name('housekeeping.rooms.inspections.start');
            Route::post('/hk/rooms/{room}/inspections/finish', [HousekeepingController::class, 'finishInspection'])
                ->name('housekeeping.rooms.inspections.finish');
        });

        Route::middleware('can:frontdesk.view')->group(function () {
            Route::get('/rooms/board', [RoomBoardController::class, 'index'])
                ->name('rooms.board');

            Route::patch('/frontdesk/rooms/{room}/hk-status', [RoomHousekeepingController::class, 'updateStatus'])
                ->middleware('idempotency')
                ->name('frontdesk.rooms.hk-status');

            Route::get('/reservations/walk-in/create', [WalkInReservationController::class, 'create'])
                ->name('reservations.walk_in.create');

            Route::post('/reservations/walk-in', [WalkInReservationController::class, 'store'])
                ->name('reservations.walk_in.store');

            Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
            Route::get('/frontdesk/dashboard', [FrontdeskController::class, 'dashboard'])->name('frontdesk.dashboard');
            Route::get('/frontdesk/forecast', [FrontdeskController::class, 'forecast'])
                ->middleware('can:night_audit.view')
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
            Route::patch('/reservations/{reservation}/guest', [ReservationCorrectionController::class, 'changeGuest'])
                ->name('reservations.guest.update');
            Route::patch('/reservations/{reservation}/offer', [ReservationCorrectionController::class, 'changeOffer'])
                ->name('reservations.offer.update');
            Route::patch('/reservations/{reservation}/stay-datetimes', [ReservationCorrectionController::class, 'overrideStayTimes'])
                ->name('reservations.stay.datetimes');
            Route::patch('/reservations/{reservation}/stay/dates', [ReservationStayController::class, 'updateDates'])
                ->middleware('idempotency')
                ->name('reservations.stay.dates');
            Route::patch('/reservations/{reservation}/stay/room', [ReservationStayController::class, 'changeRoom'])
                ->name('reservations.stay.room');
            Route::get('/frontdesk/reservations/{reservation}/details', [ReservationDetailsController::class, 'show'])
                ->name('frontdesk.reservations.details');

            Route::prefix('frontdesk')->group(function () {
                Route::get('/arrivals', [FrontdeskController::class, 'arrivals'])->name('frontdesk.arrivals');
                Route::get('/departures', [FrontdeskController::class, 'departures'])->name('frontdesk.departures');
                Route::get('/in-house', [FrontdeskController::class, 'inHouse'])->name('frontdesk.in_house');
                Route::get('/issues', [FrontdeskController::class, 'issues'])->name('frontdesk.issues');
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
            Route::get('/reservations/{reservation}/timeline', [ReservationTimelineController::class, 'show'])
                ->name('reservations.timeline');
            Route::get('/rooms/{room}/activity', [ActivityFeedController::class, 'room'])
                ->name('rooms.activity');
            Route::get('/audit/activity', [ActivityFeedController::class, 'index'])
                ->name('audit.activity');

            Route::get('/folios/{folio}', [FolioController::class, 'show'])->name('folios.show');
            Route::post('/folios/{folio}/items', [FolioController::class, 'storeItem'])->name('folios.items.store');
            Route::patch('/folios/{folio}/items/{item}', [FolioController::class, 'updateItem'])->name('folios.items.update');
            Route::delete('/folios/{folio}/items/{item}', [FolioController::class, 'destroyItem'])->name('folios.items.destroy');
            Route::post('/folios/{folio}/adjustment', [FolioAdjustmentController::class, 'store'])
                ->name('folios.adjustments.store');
            Route::post('/folios/{folio}/payments', [FolioController::class, 'storePayment'])->name('folios.payments.store');
            Route::patch('/folios/{folio}/payments/{payment}', [FolioController::class, 'updatePayment'])->name('folios.payments.update');
            Route::delete('/folios/{folio}/payments/{payment}', [FolioController::class, 'destroyPayment'])->name('folios.payments.destroy');
            Route::post('/payments/{payment}/void', [PaymentAdjustmentController::class, 'void'])->name('payments.void');
            Route::post('/payments/{payment}/refund', [PaymentAdjustmentController::class, 'refund'])->name('payments.refund');
            Route::post('/folios/{folio}/invoices', [InvoiceController::class, 'storeFromFolio'])->name('folios.invoices.store');
            Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        });

        Route::get('/maintenance', [MaintenanceTicketController::class, 'index'])
            ->middleware('can:maintenance_tickets.view')
            ->name('maintenance.index');
        Route::post('/maintenance-tickets', [MaintenanceTicketController::class, 'store'])
            ->name('maintenance-tickets.store');
        Route::patch('/maintenance-tickets/{maintenanceTicket}', [MaintenanceTicketController::class, 'update'])
            ->name('maintenance-tickets.update');
        Route::patch('/maintenance-tickets/{maintenanceTicket}/close', [MaintenanceTicketController::class, 'close'])
            ->name('maintenance-tickets.close');
        Route::post('/maintenance/interventions', [MaintenanceInterventionController::class, 'store'])
            ->name('maintenance-interventions.store');
        Route::put('/maintenance/interventions/{maintenanceIntervention}', [MaintenanceInterventionController::class, 'update'])
            ->name('maintenance-interventions.update');
        Route::get('/maintenance/interventions/{maintenanceIntervention}', [MaintenanceInterventionController::class, 'show'])
            ->name('maintenance-interventions.show');
        Route::post(
            '/maintenance/interventions/{maintenanceIntervention}/attach-ticket',
            [MaintenanceInterventionController::class, 'attachTicket'],
        )->name('maintenance-interventions.attach-ticket');
        Route::post(
            '/maintenance/interventions/{maintenanceIntervention}/detach-ticket',
            [MaintenanceInterventionController::class, 'detachTicket'],
        )->name('maintenance-interventions.detach-ticket');
        Route::post(
            '/maintenance/interventions/{maintenanceIntervention}/submit',
            [MaintenanceInterventionController::class, 'submit'],
        )->name('maintenance-interventions.submit');
        Route::post(
            '/maintenance/interventions/{maintenanceIntervention}/approve',
            [MaintenanceInterventionController::class, 'approve'],
        )->name('maintenance-interventions.approve');
        Route::post(
            '/maintenance/interventions/{maintenanceIntervention}/reject',
            [MaintenanceInterventionController::class, 'reject'],
        )->name('maintenance-interventions.reject');
        Route::post(
            '/maintenance/interventions/{maintenanceIntervention}/mark-paid',
            [MaintenanceInterventionController::class, 'markPaid'],
        )->name('maintenance-interventions.mark-paid');
        Route::post(
            '/maintenance/interventions/{maintenanceIntervention}/cost-lines',
            [MaintenanceInterventionCostController::class, 'store'],
        )->name('maintenance-interventions.cost-lines.store');
        Route::put(
            '/maintenance/interventions/{maintenanceIntervention}/cost-lines/{maintenanceInterventionCost}',
            [MaintenanceInterventionCostController::class, 'update'],
        )->name('maintenance-interventions.cost-lines.update');
        Route::delete(
            '/maintenance/interventions/{maintenanceIntervention}/cost-lines/{maintenanceInterventionCost}',
            [MaintenanceInterventionCostController::class, 'destroy'],
        )->name('maintenance-interventions.cost-lines.destroy');
        Route::post(
            '/maintenance/interventions/{maintenanceIntervention}/items',
            [MaintenanceInterventionController::class, 'storeItem'],
        )->name('maintenance-interventions.items.store');

        Route::get('/stock', [StockController::class, 'index'])
            ->name('stock.index');
        Route::get('/stock/purchases/create', [StockPurchaseController::class, 'create'])
            ->name('stock.purchases.create');
        Route::get('/stock/purchases', [StockPurchaseController::class, 'index'])
            ->name('stock.purchases.index');
        Route::get('/stock/purchases/create', [StockPurchaseController::class, 'create'])
            ->name('stock.purchases.create');
        Route::post('/stock/purchases', [StockPurchaseController::class, 'store'])
            ->name('stock.purchases.store');
        Route::get('/stock/purchases/{stockPurchase}/edit', [StockPurchaseController::class, 'edit'])
            ->name('stock.purchases.edit');
        Route::get('/stock/purchases/{stockPurchase}', [StockPurchaseController::class, 'show'])
            ->name('stock.purchases.show');
        Route::put('/stock/purchases/{stockPurchase}', [StockPurchaseController::class, 'update'])
            ->name('stock.purchases.update');
        Route::post('/stock/purchases/{stockPurchase}/receive', [StockPurchaseController::class, 'receive'])
            ->name('stock.purchases.receive');
        Route::post('/stock/purchases/{stockPurchase}/void', [StockPurchaseController::class, 'void'])
            ->name('stock.purchases.void');
        Route::get('/stock/transfers', [StockTransferController::class, 'index'])
            ->name('stock.transfers.index');
        Route::get('/stock/transfers/create', [StockTransferController::class, 'create'])
            ->name('stock.transfers.create');
        Route::post('/stock/transfers', [StockTransferController::class, 'store'])
            ->name('stock.transfers.store');
        Route::get('/stock/transfers/{stockTransfer}', [StockTransferController::class, 'show'])
            ->name('stock.transfers.show');
        Route::post('/stock/transfers/{stockTransfer}/complete', [StockTransferController::class, 'complete'])
            ->name('stock.transfers.complete');
        Route::get('/stock/inventories', [StockInventoryController::class, 'index'])
            ->name('stock.inventories.index');
        Route::get('/stock/inventories/{stockInventory}', [StockInventoryController::class, 'show'])
            ->name('stock.inventories.show');
        Route::post('/stock/inventories', [StockInventoryController::class, 'store'])
            ->name('stock.inventories.store');
        Route::post('/stock/inventories/{stockInventory}/post', [StockInventoryController::class, 'post'])
            ->name('stock.inventories.post');
        Route::get('/stock/locations', [StockLocationController::class, 'index'])
            ->name('stock.locations.index');
        Route::get('/stock/locations/{storageLocation}', [StockLocationController::class, 'show'])
            ->name('stock.locations.show');
        Route::get('/stock/movements/{stockMovement}', [StockMovementController::class, 'show'])
            ->name('stock.movements.show');

        Route::middleware('can:analytics.view')->group(function () {
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
    });

    Route::get('/activity', [ActivityController::class, 'index'])
        ->middleware(['auth', 'verified'])
        ->name('activity.index');
    Route::get('/journal', [ActivityJournalController::class, 'index'])
        ->middleware(['auth', 'verified'])
        ->name('journal.index');

    Route::prefix('settings/resources')
        ->name('ressources.')
        ->middleware(['auth', 'can:resources.view'])
        ->group(function () {
            Route::post('/active-hotel', ActiveHotelController::class)->name('active-hotel');
            Route::get('/hotel', [HotelConfigController::class, 'edit'])->name('hotel.edit');
            Route::put('/hotel', [HotelConfigController::class, 'update'])->name('hotel.update');
            Route::post('/hotel/bar-stock-location', [HotelConfigController::class, 'createBarStockLocation'])
                ->middleware('can:stock.manage_bar_settings')
                ->name('hotel.bar-stock-location');
            Route::post('/hotel/documents/logo', [HotelDocumentLogoController::class, 'store'])
                ->name('hotel.documents.logo.store');
            Route::delete('/hotel/documents/logo', [HotelDocumentLogoController::class, 'destroy'])
                ->name('hotel.documents.logo.destroy');
            Route::get('/hotel/documents/invoice-preview', [HotelDocumentPreviewController::class, 'invoice'])
                ->middleware('can:hotels.documents.update')
                ->name('hotel.documents.preview.invoice');

            Route::resource('room-types', RoomTypeController::class);
            Route::post('room-types/{roomType}/prices', [RoomTypeController::class, 'storePrice'])->name('room-types.prices.store');
            Route::resource('rooms', RoomController::class)->except(['show']);
            Route::resource('offers', OfferController::class)->except(['show']);
            Route::resource('taxes', TaxController::class)->except(['show']);
            Route::resource('payment-methods', PaymentMethodController::class)->except(['show']);
            Route::get('maintenance-types', [MaintenanceTypeController::class, 'index'])
                ->name('maintenance-types.index');
            Route::post('maintenance-types', [MaintenanceTypeController::class, 'store'])
                ->name('maintenance-types.store');
            Route::put('maintenance-types/{maintenanceType}', [MaintenanceTypeController::class, 'update'])
                ->name('maintenance-types.update');
            Route::get('technicians', [TechnicianController::class, 'index'])
                ->name('technicians.index');
            Route::post('technicians', [TechnicianController::class, 'store'])
                ->name('technicians.store');
            Route::put('technicians/{technician}', [TechnicianController::class, 'update'])
                ->name('technicians.update');
            Route::get('stock-items', [StockItemController::class, 'index'])
                ->name('stock-items.index');
            Route::post('stock-items', [StockItemController::class, 'store'])
                ->name('stock-items.store');
            Route::put('stock-items/{stockItem}', [StockItemController::class, 'update'])
                ->name('stock-items.update');
            Route::get('storage-locations', [StorageLocationController::class, 'index'])
                ->name('storage-locations.index');
            Route::post('storage-locations', [StorageLocationController::class, 'store'])
                ->name('storage-locations.store');
            Route::put('storage-locations/{storageLocation}', [StorageLocationController::class, 'update'])
                ->name('storage-locations.update');
            Route::get('bar-tables', [BarTableConfigController::class, 'index'])
                ->name('bar-tables.index');
            Route::post('bar-tables', [BarTableConfigController::class, 'store'])
                ->name('bar-tables.store');
            Route::put('bar-tables/{barTable}', [BarTableConfigController::class, 'update'])
                ->name('bar-tables.update');
            Route::resource('products', ProductController::class)->except(['show']);
            Route::resource('product-categories', ProductCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('users', UserConfigController::class)->except(['show']);
            Route::get('housekeeping-checklists', [HousekeepingChecklistController::class, 'index'])
                ->name('housekeeping-checklists.index');
            Route::post('housekeeping-checklists', [HousekeepingChecklistController::class, 'store'])
                ->name('housekeeping-checklists.store');
            Route::put('housekeeping-checklists/{housekeepingChecklist}', [HousekeepingChecklistController::class, 'update'])
                ->name('housekeeping-checklists.update');
            Route::delete('housekeeping-checklists/{housekeepingChecklist}', [HousekeepingChecklistController::class, 'destroy'])
                ->name('housekeeping-checklists.destroy');
            Route::post('housekeeping-checklists/{housekeepingChecklist}/duplicate', [HousekeepingChecklistController::class, 'duplicate'])
                ->name('housekeeping-checklists.duplicate');
            Route::post('housekeeping-checklists/{housekeepingChecklist}/items', [HousekeepingChecklistItemController::class, 'store'])
                ->name('housekeeping-checklists.items.store');
            Route::put('housekeeping-checklists/{housekeepingChecklist}/items/{item}', [HousekeepingChecklistItemController::class, 'update'])
                ->name('housekeeping-checklists.items.update');
            Route::delete('housekeeping-checklists/{housekeepingChecklist}/items/{item}', [HousekeepingChecklistItemController::class, 'destroy'])
                ->name('housekeeping-checklists.items.destroy');
            Route::post('housekeeping-checklists/{housekeepingChecklist}/items/reorder', [HousekeepingChecklistItemController::class, 'reorder'])
                ->name('housekeeping-checklists.items.reorder');

            Route::get('guests', [GuestController::class, 'index'])->name('guests.index');
            Route::get('guests/create', [GuestController::class, 'create'])->name('guests.create');
            Route::post('guests', [GuestController::class, 'store'])->name('guests.store');
            Route::get('guests/search', [GuestController::class, 'search'])->name('guests.search');
            Route::get('guests/{guest}', [GuestController::class, 'show'])->name('guests.show');
            Route::get('guests/{guest}/edit', [GuestController::class, 'edit'])->name('guests.edit');
            Route::put('guests/{guest}', [GuestController::class, 'update'])->name('guests.update');
            Route::delete('guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');

            Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
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

        $whatsappNumber = preg_replace('/\D+/', '', (string) config('serena.whatsapp_number'));
        $whatsappMessage = (string) config('serena.whatsapp_message');
        $whatsappLink = sprintf(
            'https://wa.me/%s?text=%s',
            $whatsappNumber,
            rawurlencode($whatsappMessage),
        );

        return Inertia::render('Landing/Index', [
            'canRegister' => Features::enabled(Features::registration()),
            'pricingAmount' => config('serena.pricing_amount'),
            'whatsappLink' => $whatsappLink,
            'demoSuccess' => (bool) session('demoSuccess'),
            'scrollTo' => session('scrollTo'),
            'screenshots' => [
                [
                    'title' => 'Tableau de bord FrontDesk',
                    'description' => 'Arrivées, départs et alertes en un coup d’œil.',
                ],
                [
                    'title' => 'RoomBoard',
                    'description' => 'Vue visuelle des chambres et du ménage.',
                ],
                [
                    'title' => 'Facturation & paiements',
                    'description' => 'Encaissements et folio maîtrisés.',
                ],
                [
                    'title' => 'Maintenance',
                    'description' => 'Tickets, interventions et estimations.',
                ],
            ],
        ]);
    })->name('home');

    Route::get('/demo', function () {
        return redirect()->route('home')->with('scrollTo', 'demo');
    })->name('demo');

    Route::post('/demo-request', [DemoRequestController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('demo.request');

    Route::get('/whatsapp', function () {
        $whatsappNumber = preg_replace('/\D+/', '', (string) config('serena.whatsapp_number'));
        $whatsappMessage = (string) config('serena.whatsapp_message');
        $whatsappLink = sprintf(
            'https://wa.me/%s?text=%s',
            $whatsappNumber,
            rawurlencode($whatsappMessage),
        );

        return redirect()->away($whatsappLink);
    })->name('whatsapp.redirect');

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
