<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Models\Activity;
use App\Models\BarOrder;
use App\Models\CashSession;
use App\Models\Folio;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\StockOnHand;
use App\Models\StockPurchase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('dashboard.view');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->activeHotel($request);
        $hotelId = $hotel?->id;
        $canViewAllHotels = $user->hasRole(['owner', 'manager', 'superadmin']);

        if ($canViewAllHotels && $request->boolean('all_hotels')) {
            $hotelId = null;
        } elseif ($canViewAllHotels && $request->filled('hotel_id')) {
            $hotelId = (int) $request->input('hotel_id');
        }

        $timezone = $hotel?->timezone ?? config('app.timezone');
        $today = Carbon::now($timezone)->toDateString();

        $widgets = [];
        $permissions = $user->getAllPermissions()->pluck('name')->all();
        $canFrontdesk = $user->can('frontdesk.view');
        $canHousekeeping = $user->can('housekeeping.view');
        $canPos = $user->can('pos.view');
        $canMaintenance = $user->can('maintenance_tickets.view') || $user->can('maintenance.tickets.create');
        $canStock = $user->can('stock.items.manage') || $user->can('stock.locations.manage');

        if ($canFrontdesk) {
            $widgets = array_merge($widgets, $this->receptionistWidgets($user, $hotelId, $today));
        }

        if ($canHousekeeping) {
            $widgets = array_merge($widgets, $this->housekeepingWidgets($user, $hotelId, $today));
        }

        if ($canPos) {
            $widgets = array_merge($widgets, $this->barWidgets($user, $hotelId, $today));
        }

        if ($user->hasRole(['owner', 'manager', 'superadmin'])) {
            $widgets = array_merge($widgets, $this->managerWidgets($user, $hotelId, $today));
        }

        if ($user->hasRole(['owner', 'superadmin'])) {
            $widgets = array_merge($widgets, $this->ownerWidgets($user, $hotelId, $today));
        }

        $hotels = $canViewAllHotels
            ? $user->hotels()->orderBy('name')->get(['hotels.id', 'hotels.name'])
            : [];

        return Inertia::render('Dashboard/Index', [
            'widgets' => $widgets,
            'filters' => [
                'all_hotels' => $request->boolean('all_hotels'),
                'hotel_id' => $request->input('hotel_id'),
            ],
            'canViewAllHotels' => $canViewAllHotels,
            'hotels' => $hotels,
            'can' => [
                'frontdesk' => $canFrontdesk,
                'housekeeping' => $canHousekeeping,
                'pos' => $canPos,
                'maintenance' => $canMaintenance,
                'stock' => $canStock,
                'cash' => $user->can('cash_sessions.view'),
                'journal' => $user->can('journal.view'),
            ],
            'permissions' => $permissions,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function receptionistWidgets(User $user, ?int $hotelId, string $today): array
    {
        $reservations = $this->reservationQuery($user, $hotelId);
        $rooms = $this->roomQuery($user, $hotelId);

        $arrivals = (clone $reservations)
            ->whereDate('check_in_date', $today)
            ->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])
            ->count();

        $departures = (clone $reservations)
            ->whereDate('check_out_date', $today)
            ->whereIn('status', [Reservation::STATUS_IN_HOUSE, Reservation::STATUS_CONFIRMED])
            ->count();

        $inHouse = (clone $reservations)
            ->where('status', Reservation::STATUS_IN_HOUSE)
            ->count();

        $walkins = (clone $reservations)
            ->whereDate('check_in_date', $today)
            ->where('source', 'walk_in')
            ->count();

        $roomsOccupied = (clone $rooms)
            ->whereIn('status', [Room::STATUS_OCCUPIED, Room::STATUS_IN_USE])
            ->count();

        $roomsAvailable = (clone $rooms)
            ->where('status', Room::STATUS_AVAILABLE)
            ->where('hk_status', Room::HK_STATUS_INSPECTED)
            ->count();

        $roomsDirty = (clone $rooms)
            ->where('hk_status', Room::HK_STATUS_DIRTY)
            ->count();

        $roomsToInspect = (clone $rooms)
            ->where('hk_status', Room::HK_STATUS_AWAITING_INSPECTION)
            ->count();

        $overstays = (clone $reservations)
            ->where('status', Reservation::STATUS_IN_HOUSE)
            ->whereDate('check_out_date', '<', $today)
            ->count();

        $roomsOutOfOrder = (clone $rooms)
            ->where('status', Room::STATUS_OUT_OF_ORDER)
            ->count();

        $unpaidBalance = $this->unpaidBalanceCount($user, $hotelId);

        return [
            $this->widget('today', 'Aujourd’hui', [
                ['label' => 'Arrivées', 'value' => $arrivals],
                ['label' => 'Départs', 'value' => $departures],
                ['label' => 'En séjour', 'value' => $inHouse],
                ['label' => 'Walk-in', 'value' => $walkins],
            ], [
                ['label' => 'RoomBoard', 'href' => '/frontdesk/dashboard'],
            ]),
            $this->widget('rooms', 'Chambres', [
                ['label' => 'Occupées', 'value' => $roomsOccupied],
                ['label' => 'Disponibles', 'value' => $roomsAvailable],
                ['label' => 'Sales', 'value' => $roomsDirty],
                ['label' => 'À inspecter', 'value' => $roomsToInspect],
            ], [
                ['label' => 'RoomBoard', 'href' => '/frontdesk/dashboard'],
            ]),
            $this->widget('alerts', 'Alertes', [
                ['label' => 'Overstays', 'value' => $overstays],
                ['label' => 'Chambres HS', 'value' => $roomsOutOfOrder],
                ['label' => 'Soldes impayés', 'value' => $unpaidBalance],
            ], [
                ['label' => 'FrontDesk', 'href' => '/frontdesk/dashboard'],
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function housekeepingWidgets(User $user, ?int $hotelId, string $today): array
    {
        $rooms = $this->roomQuery($user, $hotelId);

        $dirty = (clone $rooms)
            ->where('hk_status', Room::HK_STATUS_DIRTY)
            ->count();

        $redo = (clone $rooms)
            ->where('hk_status', Room::HK_STATUS_REDO)
            ->count();

        $toInspect = (clone $rooms)
            ->where('hk_status', Room::HK_STATUS_AWAITING_INSPECTION)
            ->count();

        $cleanedToday = $this->hkActivityCount($user, $hotelId, $today, Room::HK_STATUS_AWAITING_INSPECTION);
        $inspectedToday = $this->hkActivityCount($user, $hotelId, $today, Room::HK_STATUS_INSPECTED);

        return [
            $this->widget('hk_tasks', 'À faire', [
                ['label' => 'Sales', 'value' => $dirty],
                ['label' => 'À refaire', 'value' => $redo],
                ['label' => 'À inspecter', 'value' => $toInspect],
            ], [
                ['label' => 'RoomBoard', 'href' => '/frontdesk/dashboard'],
            ]),
            $this->widget('hk_progress', 'Progression', [
                ['label' => 'Nettoyées', 'value' => $cleanedToday],
                ['label' => 'Inspectées', 'value' => $inspectedToday],
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function barWidgets(User $user, ?int $hotelId, string $today): array
    {
        $openOrders = $this->barOrderQuery($user, $hotelId)
            ->whereIn('status', [BarOrder::STATUS_DRAFT, BarOrder::STATUS_OPEN])
            ->whereNotNull('bar_table_id');

        $openTables = (clone $openOrders)
            ->distinct('bar_table_id')
            ->count('bar_table_id');

        $unpaidBills = (clone $openOrders)->count();

        $openSession = $this->cashSessionQuery($user, $hotelId)
            ->where('type', 'bar')
            ->where('status', 'open')
            ->latest('started_at')
            ->first();

        $barSalesToday = $this->paymentsQuery($user, $hotelId)
            ->whereDate('paid_at', $today)
            ->whereHas('cashSession', fn (Builder $query) => $query->where('type', 'bar'))
            ->sum('amount');

        $lowStockBar = $this->lowStockCount($user, $hotelId, 'bar');

        return [
            $this->widget('bar_cash', 'Caisse', [
                ['label' => 'Session', 'value' => $openSession ? 'Ouverte' : 'Fermée'],
                ['label' => 'Ventes', 'value' => $this->formatMoney($barSalesToday)],
            ], [
                ['label' => 'POS', 'href' => '/pos'],
                ['label' => 'Caisse', 'href' => '/cash'],
            ]),
            $this->widget('bar_orders', 'Commandes', [
                ['label' => 'Tables ouvertes', 'value' => $openTables],
                ['label' => 'Commandes en cours', 'value' => $unpaidBills],
            ], [
                ['label' => 'POS', 'href' => '/pos'],
            ]),
            $this->widget('bar_stock', 'Stock bar', [
                ['label' => 'Ruptures / seuil', 'value' => $lowStockBar],
            ], [
                ['label' => 'Stock', 'href' => '/stock'],
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function managerWidgets(User $user, ?int $hotelId, string $today): array
    {
        $paymentsByMethod = $this->paymentsQuery($user, $hotelId)
            ->whereDate('paid_at', $today)
            ->selectRaw('payment_methods.name as method_name, sum(payments.amount) as total')
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->groupBy('payment_methods.name')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->method_name ?? 'Autre',
                'value' => $this->formatMoney((float) $row->total),
            ])
            ->all();

        $openSessions = $this->cashSessionQuery($user, $hotelId)
            ->where('status', 'open')
            ->count();

        $closedToday = $this->cashSessionQuery($user, $hotelId)
            ->where('status', 'closed')
            ->whereDate('ended_at', $today)
            ->count();

        $openTickets = $this->maintenanceTicketQuery($user, $hotelId)
            ->whereIn('status', [MaintenanceTicket::STATUS_OPEN, MaintenanceTicket::STATUS_IN_PROGRESS])
            ->count();

        $blockingTickets = $this->maintenanceTicketQuery($user, $hotelId)
            ->whereIn('status', [MaintenanceTicket::STATUS_OPEN, MaintenanceTicket::STATUS_IN_PROGRESS])
            ->where('blocks_sale', true)
            ->count();

        $interventionsSubmitted = $this->maintenanceInterventionQuery($user, $hotelId)
            ->where('accounting_status', MaintenanceIntervention::STATUS_SUBMITTED)
            ->count();

        $lowStock = $this->lowStockCount($user, $hotelId, null);

        $pendingPurchases = $this->stockPurchaseQuery($user, $hotelId)
            ->where('status', StockPurchase::STATUS_DRAFT)
            ->count();

        return [
            $this->widget('revenues', 'Revenus (Business day)', $paymentsByMethod, [
                ['label' => 'Journal', 'href' => '/journal'],
            ]),
            $this->widget('cash_sessions', 'Caisses', [
                ['label' => 'Sessions ouvertes', 'value' => $openSessions],
                ['label' => 'Clôturées aujourd’hui', 'value' => $closedToday],
            ], [
                ['label' => 'Caisse', 'href' => '/cash'],
            ]),
            $this->widget('maintenance', 'Maintenance', [
                ['label' => 'Tickets ouverts', 'value' => $openTickets],
                ['label' => 'Bloquants', 'value' => $blockingTickets],
                ['label' => 'À valider', 'value' => $interventionsSubmitted],
            ], [
                ['label' => 'Maintenance', 'href' => '/maintenance'],
            ]),
            $this->widget('stock', 'Stock', [
                ['label' => 'Seuils bas', 'value' => $lowStock],
                ['label' => 'Achats en brouillon', 'value' => $pendingPurchases],
            ], [
                ['label' => 'Stock', 'href' => '/stock'],
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ownerWidgets(User $user, ?int $hotelId, string $today): array
    {
        $sevenDays = Carbon::parse($today)->addDays(7)->toDateString();

        $soldNextSeven = $this->reservationQuery($user, $hotelId)
            ->whereDate('check_in_date', '>=', $today)
            ->whereDate('check_in_date', '<=', $sevenDays)
            ->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED, Reservation::STATUS_IN_HOUSE])
            ->count();

        $overstays = $this->reservationQuery($user, $hotelId)
            ->where('status', Reservation::STATUS_IN_HOUSE)
            ->whereDate('check_out_date', '<', $today)
            ->count();

        $negativeStock = StockOnHand::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->where('quantity_on_hand', '<', 0)
            ->count();

        return [
            $this->widget('owner_forecast', '7 jours', [
                ['label' => 'Nuits vendues', 'value' => $soldNextSeven],
            ]),
            $this->widget('owner_anomalies', 'Top anomalies', [
                ['label' => 'Overstays', 'value' => $overstays],
                ['label' => 'Stock négatif', 'value' => $negativeStock],
            ], [
                ['label' => 'Journal', 'href' => '/journal'],
            ]),
        ];
    }

    /**
     * @return array{key: string, title: string, stats: list<array{label: string, value: mixed}>, actions: list<array{label: string, href: string}>}
     */
    private function widget(string $key, string $title, array $stats, array $actions = []): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'stats' => $stats,
            'actions' => $actions,
        ];
    }

    private function reservationQuery(User $user, ?int $hotelId): Builder
    {
        return Reservation::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId));
    }

    private function roomQuery(User $user, ?int $hotelId): Builder
    {
        return Room::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId));
    }

    private function paymentsQuery(User $user, ?int $hotelId): Builder
    {
        return Payment::query()
            ->where('payments.tenant_id', $user->tenant_id)
            ->whereNull('payments.deleted_at')
            ->when($hotelId, fn ($q) => $q->where('payments.hotel_id', $hotelId));
    }

    private function cashSessionQuery(User $user, ?int $hotelId): Builder
    {
        return CashSession::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId));
    }

    private function maintenanceTicketQuery(User $user, ?int $hotelId): Builder
    {
        return MaintenanceTicket::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId));
    }

    private function maintenanceInterventionQuery(User $user, ?int $hotelId): Builder
    {
        return MaintenanceIntervention::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId));
    }

    private function barOrderQuery(User $user, ?int $hotelId): Builder
    {
        return BarOrder::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId));
    }

    private function stockPurchaseQuery(User $user, ?int $hotelId): Builder
    {
        return StockPurchase::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId));
    }

    private function hkActivityCount(User $user, ?int $hotelId, string $today, string $toStatus): int
    {
        return Activity::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->where('log_name', 'room')
            ->whereDate('created_at', $today)
            ->where('properties->to_hk_status', $toStatus)
            ->count();
    }

    private function unpaidBalanceCount(User $user, ?int $hotelId): int
    {
        $query = Folio::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->whereHas('reservation', fn ($q) => $q->where('status', Reservation::STATUS_IN_HOUSE))
            ->whereRaw(
                '(select COALESCE(SUM(total_amount), 0) from folio_items where folio_items.folio_id = folios.id and folio_items.deleted_at is null)
                - (select COALESCE(SUM(amount), 0) from payments where payments.folio_id = folios.id and payments.deleted_at is null) > 0',
            );

        return $query->count();
    }

    private function lowStockCount(User $user, ?int $hotelId, ?string $category): int
    {
        return StockOnHand::query()
            ->join('stock_items', 'stock_items.id', '=', 'stock_on_hand.stock_item_id')
            ->where('stock_on_hand.tenant_id', $user->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('stock_on_hand.hotel_id', $hotelId))
            ->when($category, fn ($q) => $q->where('stock_items.item_category', $category))
            ->whereNotNull('stock_items.reorder_point')
            ->whereColumn('stock_on_hand.quantity_on_hand', '<=', 'stock_items.reorder_point')
            ->count();
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 0, '.', ' ');
    }
}
