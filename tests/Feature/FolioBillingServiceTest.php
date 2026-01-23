<?php

use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Services\FolioBillingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Demo Tenant',
        'slug' => 'demo-tenant-'.Str::random(4),
        'plan' => 'standard',
    ]);

    $this->hotel = Hotel::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Demo Hotel',
        'code' => 'DEMO',
        'currency' => 'XAF',
        'timezone' => 'Africa/Libreville',
        'address' => 'Main street',
        'city' => 'Libreville',
        'country' => 'GA',
        'check_in_time' => '14:00:00',
        'check_out_time' => '12:00:00',
    ]);

    $this->roomType = RoomType::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'name' => 'Deluxe',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 10000,
        'description' => 'Deluxe room',
    ]);

    $this->room = Room::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'number' => '101',
        'floor' => '1',
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $this->guest = Guest::query()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '123456',
        'document_number' => 'ID-123',
        'address' => 'Main street',
    ]);

    $this->reservation = Reservation::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'guest_id' => $this->guest->id,
        'room_type_id' => $this->roomType->id,
        'room_id' => $this->room->id,
        'offer_id' => null,
        'code' => 'RES-1001',
        'status' => Reservation::STATUS_CONFIRMED,
        'source' => 'direct',
        'offer_name' => null,
        'offer_kind' => null,
        'adults' => 2,
        'children' => 0,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->copy()->addDay()->toDateString(),
        'expected_arrival_time' => '15:00:00',
        'actual_check_in_at' => null,
        'actual_check_out_at' => null,
        'currency' => 'XAF',
        'unit_price' => 10000,
        'base_amount' => 10000,
        'tax_amount' => 1900,
        'total_amount' => 11900,
        'notes' => null,
        'booked_by_user_id' => null,
    ]);
});

it('ensures a main folio is created for a reservation', function (): void {
    $service = app(FolioBillingService::class);

    expect(Folio::query()->count())->toBe(0);

    $folio = $service->ensureMainFolioForReservation($this->reservation);

    expect($folio)->not->toBeNull()
        ->and($folio->is_main)->toBeTrue()
        ->and($folio->reservation_id)->toBe($this->reservation->id)
        ->and($folio->code)->toBe('FOL-'.$this->reservation->code);

    expect(Folio::query()->count())->toBe(1);

    $sameFolio = $service->ensureMainFolioForReservation($this->reservation->fresh());

    expect($sameFolio->id)->toBe($folio->id);
});

it('generates an invoice with items for a folio', function (): void {
    Carbon::setTestNow('2025-01-02 10:00:00');

    $folio = Folio::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'reservation_id' => $this->reservation->id,
        'guest_id' => $this->guest->id,
        'code' => 'FOL-'.$this->reservation->code,
        'status' => 'open',
        'is_main' => true,
        'type' => 'reservation',
        'origin' => 'reservation',
        'currency' => 'XAF',
        'billing_name' => $this->guest->full_name,
        'opened_at' => now(),
    ]);

    FolioItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'folio_id' => $folio->id,
        'product_id' => null,
        'date' => now()->toDateString(),
        'description' => 'Nuitée',
        'type' => 'room',
        'account_code' => 'ROOM',
        'quantity' => 1,
        'unit_price' => 10000,
        'base_amount' => 10000,
        'tax_amount' => 1900,
        'total_amount' => 11900,
    ]);

    FolioItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'folio_id' => $folio->id,
        'product_id' => null,
        'date' => now()->toDateString(),
        'description' => 'Petit-déjeuner',
        'type' => 'food',
        'account_code' => 'F&B',
        'quantity' => 1,
        'unit_price' => 5000,
        'base_amount' => 5000,
        'tax_amount' => 950,
        'total_amount' => 5950,
    ]);

    $service = app(FolioBillingService::class);

    $invoice = $service->generateInvoiceFromFolio($folio);

    expect($invoice)->not->toBeNull()
        ->and($invoice->total_amount)->toBe(17850.0)
        ->and($invoice->items()->count())->toBe(2);

    expect(Invoice::query()->count())->toBe(1);

    Carbon::setTestNow();
});

it('adds descriptive stay adjustment charges with quantity and unit price', function (): void {
    $service = app(FolioBillingService::class);
    $folio = $service->ensureMainFolioForReservation($this->reservation);

    $service->addStayAdjustment($this->reservation, 20000, 'Prolongation de séjour', [
        'line_description' => 'Prolongation de séjour - Offre Premium · Séjour du 10/05/2025 - 12/05/2025',
        'quantity' => 2,
        'unit_price' => 10000,
        'meta' => [
            'previous_check_out' => '2025-05-10',
            'new_check_out' => '2025-05-12',
        ],
    ]);

    $folio->refresh();
    $item = $folio->items()->latest()->first();

    expect($item->description)->toBe('Prolongation de séjour - Offre Premium · Séjour du 10/05/2025 - 12/05/2025')
        ->and($item->quantity)->toBe(2.0)
        ->and($item->unit_price)->toBe(10000.0)
        ->and($item->base_amount)->toBe(20000.0)
        ->and($item->type)->toBe('stay_adjustment')
        ->and($item->meta['previous_check_out'])->toBe('2025-05-10')
        ->and($folio->balance)->toBe(20000.0);
});

it('syncs fixed billing stay items as a single unit', function (): void {
    $offer = Offer::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'name' => 'Week-end',
        'kind' => 'weekend',
        'billing_mode' => 'fixed',
        'is_active' => true,
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'offer_id' => $offer->id,
        'room_type_id' => $this->roomType->id,
        'currency' => 'XAF',
        'price' => 55000,
    ]);

    $this->reservation->update([
        'offer_id' => $offer->id,
        'offer_name' => $offer->name,
        'offer_kind' => $offer->kind,
        'check_in_date' => '2025-05-01 12:00:00',
        'check_out_date' => '2025-05-03 12:00:00',
        'unit_price' => 55000,
        'base_amount' => 55000,
        'total_amount' => 55000,
    ]);

    $service = app(FolioBillingService::class);
    $service->syncStayChargeFromReservation($this->reservation->fresh());

    $folio = $service->ensureMainFolioForReservation($this->reservation);
    $stayItem = $folio->items()->where('is_stay_item', true)->first();

    expect($stayItem)->not->toBeNull()
        ->and($stayItem->quantity)->toBe(1.0)
        ->and($stayItem->unit_price)->toBe(55000.0)
        ->and($stayItem->base_amount)->toBe(55000.0);
});

it('falls back to default stay adjustment description when no context is provided', function (): void {
    $service = app(FolioBillingService::class);
    $folio = $service->ensureMainFolioForReservation($this->reservation);

    $service->addStayAdjustment($this->reservation, 5000, 'Changement de chambre');

    $folio->refresh();
    $item = $folio->items()->latest()->first();

    expect($item->description)->toBe('Changement de chambre - Séjour')
        ->and($item->quantity)->toBe(1.0)
        ->and($item->unit_price)->toBe(5000.0)
        ->and($folio->balance)->toBe(5000.0);
});

it('supports negative stay adjustment totals for reductions', function (): void {
    $service = app(FolioBillingService::class);
    $folio = $service->ensureMainFolioForReservation($this->reservation);

    $service->addStayAdjustment($this->reservation, -15000, 'Réduction de séjour', [
        'line_description' => 'Réduction de séjour - Offre Premium · Séjour du 15/05/2025 - 13/05/2025',
        'quantity' => 1,
        'unit_price' => -15000,
    ]);

    $folio->refresh();
    $item = $folio->items()->latest()->first();

    expect($item->unit_price)->toBe(-15000.0)
        ->and($item->base_amount)->toBe(-15000.0)
        ->and($item->total_amount)->toBe(-15000.0)
        ->and($folio->balance)->toBe(-15000.0);
});

it('soft deletes stay items and recreates segments on room change', function (): void {
    $service = app(FolioBillingService::class);
    $this->reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => '2025-05-01 15:00:00',
        'check_out_date' => '2025-05-05 11:00:00',
        'unit_price' => 80000,
    ]);

    $service->syncStayChargeFromReservation($this->reservation);
    $folio = $service->ensureMainFolioForReservation($this->reservation);
    $stayItem = $folio->items()->where('is_stay_item', true)->first();

    $newRoomType = RoomType::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'name' => 'Suite',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 150000,
        'description' => 'Suite',
    ]);

    $newRoom = Room::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $newRoomType->id,
        'number' => '201',
        'floor' => '2',
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $pivot = Carbon::parse('2025-05-03 00:00:00');

    $service->resegmentStayForRoomChange(
        $this->reservation->fresh(),
        $this->room,
        $newRoom,
        $pivot,
        80000,
        120000,
        'used',
    );

    $folio->refresh();
    $activeItems = $folio->items()->where('is_stay_item', true)->get();
    $allItems = $folio->items()->withTrashed()->where('is_stay_item', true)->get();

    expect($activeItems)->toHaveCount(2)
        ->and($allItems)->toHaveCount(2)
        ->and($allItems->firstWhere('id', $stayItem->id)?->trashed())->toBeFalse();
});

it('preserves stay items before the pivot when resegmenting a room change', function (): void {
    $service = app(FolioBillingService::class);
    $this->reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => '2025-05-01 00:00:00',
        'check_out_date' => '2025-05-05 00:00:00',
        'unit_price' => 80000,
    ]);

    $folio = $service->ensureMainFolioForReservation($this->reservation);

    $pastItem = FolioItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'folio_id' => $folio->id,
        'is_stay_item' => true,
        'date' => '2025-05-01',
        'description' => 'Séjour du 2025-05-01 au 2025-05-03',
        'type' => 'stay',
        'quantity' => 2,
        'unit_price' => 80000,
        'base_amount' => 160000,
        'tax_amount' => 0,
        'total_amount' => 160000,
        'meta' => [
            'segment_start' => '2025-05-01',
            'segment_end' => '2025-05-03',
        ],
    ]);

    $futureItem = FolioItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'folio_id' => $folio->id,
        'is_stay_item' => true,
        'date' => '2025-05-03',
        'description' => 'Séjour du 2025-05-03 au 2025-05-05',
        'type' => 'stay',
        'quantity' => 2,
        'unit_price' => 80000,
        'base_amount' => 160000,
        'tax_amount' => 0,
        'total_amount' => 160000,
        'meta' => [
            'segment_start' => '2025-05-03',
            'segment_end' => '2025-05-05',
        ],
    ]);

    $newRoom = Room::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'number' => '202',
        'floor' => '2',
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $service->resegmentStayForRoomChange(
        $this->reservation->fresh(),
        $this->room,
        $newRoom,
        Carbon::parse('2025-05-03 00:00:00'),
        80000,
        120000,
        'used',
    );

    $pastItem = FolioItem::withTrashed()->find($pastItem->id);
    $futureItem = FolioItem::withTrashed()->find($futureItem->id);
    $activeItems = $folio->items()->where('is_stay_item', true)->get();
    $newSegment = $activeItems->firstWhere('meta.segment_start', '2025-05-03');

    expect($pastItem?->trashed())->toBeFalse()
        ->and($futureItem?->trashed())->toBeTrue()
        ->and($activeItems)->toHaveCount(2)
        ->and($newSegment)->not->toBeNull()
        ->and($newSegment->meta['segment_end'])->toBe('2025-05-05');
});

it('only updates stay items after the pivot and keeps earlier segments intact', function (): void {
    $service = app(FolioBillingService::class);
    $this->reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => '2025-01-01 00:00:00',
        'check_out_date' => '2025-01-25 00:00:00',
        'unit_price' => 10000,
    ]);

    $folio = $service->ensureMainFolioForReservation($this->reservation);

    $segmentA = FolioItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'folio_id' => $folio->id,
        'is_stay_item' => true,
        'date' => '2025-01-01',
        'description' => 'Séjour du 2025-01-01 au 2025-01-15',
        'type' => 'stay',
        'quantity' => 14,
        'unit_price' => 10000,
        'base_amount' => 140000,
        'tax_amount' => 0,
        'total_amount' => 140000,
        'meta' => [
            'segment_start' => '2025-01-01',
            'segment_end' => '2025-01-15',
        ],
    ]);

    $segmentB = FolioItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'folio_id' => $folio->id,
        'is_stay_item' => true,
        'date' => '2025-01-15',
        'description' => 'Séjour du 2025-01-15 au 2025-01-20',
        'type' => 'stay',
        'quantity' => 5,
        'unit_price' => 10000,
        'base_amount' => 50000,
        'tax_amount' => 0,
        'total_amount' => 50000,
        'meta' => [
            'segment_start' => '2025-01-15',
            'segment_end' => '2025-01-20',
        ],
    ]);

    $segmentC = FolioItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'folio_id' => $folio->id,
        'is_stay_item' => true,
        'date' => '2025-01-20',
        'description' => 'Séjour du 2025-01-20 au 2025-01-25',
        'type' => 'stay',
        'quantity' => 5,
        'unit_price' => 10000,
        'base_amount' => 50000,
        'tax_amount' => 0,
        'total_amount' => 50000,
        'meta' => [
            'segment_start' => '2025-01-20',
            'segment_end' => '2025-01-25',
        ],
    ]);

    $newRoom = Room::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'number' => '205',
        'floor' => '2',
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $service->resegmentStayForRoomChange(
        $this->reservation->fresh(),
        $this->room,
        $newRoom,
        Carbon::parse('2025-01-22 10:00:00'),
        10000,
        15000,
        'used',
    );

    $segmentA = FolioItem::withTrashed()->find($segmentA->id);
    $segmentB = FolioItem::withTrashed()->find($segmentB->id);
    $segmentC = FolioItem::withTrashed()->find($segmentC->id);
    $activeItems = $folio->items()->where('is_stay_item', true)->get();
    $afterPivot = $activeItems->firstWhere('meta.segment_start', '2025-01-22');

    expect($segmentA?->trashed())->toBeFalse()
        ->and($segmentB?->trashed())->toBeFalse()
        ->and($segmentC?->trashed())->toBeFalse()
        ->and($segmentC?->meta['segment_end'])->toBe('2025-01-22')
        ->and($afterPivot)->not->toBeNull()
        ->and($afterPivot->meta['segment_end'])->toBe('2025-01-25')
        ->and($activeItems)->toHaveCount(4);
});

it('keeps stay extension segments intact when description uses day month year format', function (): void {
    $service = app(FolioBillingService::class);
    $this->reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => '2025-01-01 00:00:00',
        'check_out_date' => '2025-01-25 00:00:00',
        'unit_price' => 20000,
    ]);

    $folio = $service->ensureMainFolioForReservation($this->reservation);

    $extensionItem = FolioItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'folio_id' => $folio->id,
        'is_stay_item' => true,
        'date' => '2025-01-15',
        'description' => 'Prolongation de séjour - Nuitée · Séjour du 15/01/2025 - 20/01/2025',
        'type' => 'stay_extension',
        'quantity' => 5,
        'unit_price' => 20000,
        'base_amount' => 100000,
        'tax_amount' => 0,
        'total_amount' => 100000,
        'meta' => [
            'previous_check_out' => '2025-01-15',
            'new_check_out' => '2025-01-20',
        ],
    ]);

    $newRoom = Room::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'number' => '206',
        'floor' => '2',
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $service->resegmentStayForRoomChange(
        $this->reservation->fresh(),
        $this->room,
        $newRoom,
        Carbon::parse('2025-01-22 10:00:00'),
        20000,
        25000,
        'used',
    );

    $extensionItem = FolioItem::withTrashed()->find($extensionItem->id);

    expect($extensionItem?->trashed())->toBeFalse()
        ->and($extensionItem?->quantity)->toBe(5.0)
        ->and($extensionItem?->description)->toBe('Prolongation de séjour - Nuitée · Séjour du 15/01/2025 - 20/01/2025');
});

it('keeps stay extensions bounded and creates a transfer segment on room move', function (): void {
    $service = app(FolioBillingService::class);
    $this->room->update(['number' => '105']);
    $this->reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => '2026-01-01 00:00:00',
        'check_out_date' => '2026-01-28 00:00:00',
        'unit_price' => 20000,
    ]);

    $folio = $service->ensureMainFolioForReservation($this->reservation);

    FolioItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'folio_id' => $folio->id,
        'is_stay_item' => true,
        'date' => '2026-01-01',
        'description' => 'Nuitée · Séjour du 2026-01-01 - 2026-01-10 (Chambre 105)',
        'type' => 'stay',
        'quantity' => 9,
        'unit_price' => 20000,
        'base_amount' => 180000,
        'tax_amount' => 0,
        'total_amount' => 180000,
        'meta' => [
            'segment_start' => '2026-01-01',
            'segment_end' => '2026-01-10',
            'segment_version' => 1,
            'room_id' => $this->room->id,
            'room_number' => '105',
            'offer_kind' => 'night',
            'offer_name' => 'Nuitée',
        ],
    ]);

    $extensions = [
        ['start' => '2026-01-10', 'end' => '2026-01-17', 'qty' => 7],
        ['start' => '2026-01-17', 'end' => '2026-01-21', 'qty' => 4],
        ['start' => '2026-01-21', 'end' => '2026-01-28', 'qty' => 7],
    ];

    foreach ($extensions as $extension) {
        FolioItem::query()->create([
            'tenant_id' => $this->tenant->id,
            'hotel_id' => $this->hotel->id,
            'folio_id' => $folio->id,
            'is_stay_item' => true,
            'date' => $extension['start'],
            'description' => sprintf(
                'Prolongation de séjour - Nuitée · Séjour du %s - %s (Chambre 105)',
                $extension['start'],
                $extension['end'],
            ),
            'type' => 'stay_extension',
            'quantity' => $extension['qty'],
            'unit_price' => 20000,
            'base_amount' => $extension['qty'] * 20000,
            'tax_amount' => 0,
            'total_amount' => $extension['qty'] * 20000,
            'meta' => [
                'segment_start' => $extension['start'],
                'segment_end' => $extension['end'],
                'segment_version' => 1,
                'room_id' => $this->room->id,
                'room_number' => '105',
                'offer_kind' => 'night',
                'offer_name' => 'Nuitée',
            ],
        ]);
    }

    $newRoom = Room::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'number' => '107',
        'floor' => '1',
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $service->resegmentStayForRoomChange(
        $this->reservation->fresh(),
        $this->room->fresh(),
        $newRoom,
        Carbon::parse('2026-01-23 10:00:00'),
        20000,
        25000,
        'used',
    );

    $folio->refresh();

    $extensionSegment = $folio->items()
        ->where('type', 'stay_extension')
        ->where('meta->segment_start', '2026-01-21')
        ->first();

    $transferSegment = $folio->items()
        ->where('type', 'stay')
        ->where('meta->segment_start', '2026-01-23')
        ->where('meta->room_number', '107')
        ->first();

    expect($extensionSegment)->not->toBeNull()
        ->and($extensionSegment?->quantity)->toBe(2.0)
        ->and($extensionSegment?->meta['segment_end'])->toBe('2026-01-23')
        ->and($extensionSegment?->description)->toBe(
            'Prolongation de séjour - Nuitée · Séjour du 2026-01-21 - 2026-01-23 (Chambre 105)',
        );

    expect($transferSegment)->not->toBeNull()
        ->and($transferSegment?->quantity)->toBe(5.0)
        ->and($transferSegment?->description)->toBe(
            'Transfert de séjour - Nuitée · Séjour du 2026-01-23 - 2026-01-28 (Chambre 107)',
        );

    $unchangedExtension = $folio->items()
        ->where('type', 'stay_extension')
        ->where('meta->segment_start', '2026-01-17')
        ->first();

    expect($unchangedExtension)->not->toBeNull()
        ->and($unchangedExtension?->quantity)->toBe(4.0)
        ->and($unchangedExtension?->meta['segment_end'])->toBe('2026-01-21');
});

it('replaces all stay items when pivot is at check in for not used rooms', function (): void {
    $service = app(FolioBillingService::class);
    $this->reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => '2025-02-01 00:00:00',
        'check_out_date' => '2025-02-05 00:00:00',
        'unit_price' => 12000,
    ]);

    $folio = $service->ensureMainFolioForReservation($this->reservation);

    FolioItem::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'folio_id' => $folio->id,
        'is_stay_item' => true,
        'date' => '2025-02-01',
        'description' => 'Séjour du 2025-02-01 au 2025-02-05',
        'type' => 'stay',
        'quantity' => 4,
        'unit_price' => 12000,
        'base_amount' => 48000,
        'tax_amount' => 0,
        'total_amount' => 48000,
        'meta' => [
            'segment_start' => '2025-02-01',
            'segment_end' => '2025-02-05',
        ],
    ]);

    $newRoom = Room::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'number' => '210',
        'floor' => '2',
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $service->resegmentStayForRoomChange(
        $this->reservation->fresh(),
        $this->room,
        $newRoom,
        Carbon::parse('2025-02-03 10:00:00'),
        12000,
        15000,
        'not_used',
    );

    $activeItems = $folio->items()->where('is_stay_item', true)->get();
    $allItems = $folio->items()->withTrashed()->where('is_stay_item', true)->get();

    expect($activeItems)->toHaveCount(1)
        ->and($allItems)->toHaveCount(2)
        ->and($activeItems->first()->meta['segment_start'])->toBe('2025-02-01')
        ->and($activeItems->first()->meta['segment_end'])->toBe('2025-02-05')
        ->and($activeItems->first()->meta['room_id'])->toBe($newRoom->id);
});

it('does not allow stay item soft deletes once an invoice is issued', function (): void {
    $service = app(FolioBillingService::class);
    $this->reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => '2025-05-01 15:00:00',
        'check_out_date' => '2025-05-05 11:00:00',
        'unit_price' => 80000,
    ]);

    $service->syncStayChargeFromReservation($this->reservation);
    $folio = $service->ensureMainFolioForReservation($this->reservation);

    $service->generateInvoiceFromFolio($folio);

    $newRoom = Room::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'number' => '202',
        'floor' => '2',
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $service->resegmentStayForRoomChange(
        $this->reservation->fresh(),
        $this->room,
        $newRoom,
        Carbon::parse('2025-05-03'),
        80000,
        120000,
        'used',
    );

    $folio->refresh();

    expect($folio->items()->withTrashed()->where('is_stay_item', true)->count())->toBe(1)
        ->and($folio->items()->where('is_stay_item', true)->count())->toBe(1);
});

it('excludes soft-deleted items when issuing invoices', function (): void {
    $service = app(FolioBillingService::class);
    $service->syncStayChargeFromReservation($this->reservation);
    $folio = $service->ensureMainFolioForReservation($this->reservation);

    $folio->addCharge([
        'description' => 'Petit-déjeuner',
        'quantity' => 2,
        'unit_price' => 5000,
        'tax_amount' => 0,
    ]);

    $folio->items()->where('is_stay_item', true)->first()?->delete();

    $invoice = $service->generateInvoiceFromFolio($folio->fresh());

    expect($invoice->items()->count())->toBe(1)
        ->and($invoice->items()->first()->description)->toBe('Petit-déjeuner')
        ->and($invoice->total_amount)->toBe(10000.0);
});
