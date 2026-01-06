<?php

use App\Models\Hotel;
use App\Models\Room;
use App\Services\NightAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('returns empty values when no data exists for the business date', function () {
    $tenantId = 1;
    $hotel = Hotel::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Test Hotel',
        'code' => 'TH',
        'currency' => 'XAF',
        'timezone' => 'UTC',
    ]);

    Room::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotel->id,
        'number' => '101',
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    Room::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotel->id,
        'number' => '102',
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $service = app(NightAuditService::class);

    $report = $service->generate($tenantId, $hotel->id, Carbon::parse('2025-01-01'));

    expect($report['occupancy']['total_rooms'])->toBe(2)
        ->and($report['occupancy']['occupied_rooms'])->toBe(0)
        ->and($report['revenue']['total_revenue'])->toBe(0.0)
        ->and($report['payments_by_method'])->toBeArray()
        ->and($report['cash_reconciliation']['sessions'])->toBeArray();
});
