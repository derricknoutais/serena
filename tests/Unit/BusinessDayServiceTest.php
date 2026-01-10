<?php

use App\Models\Hotel;
use App\Models\Tenant;
use App\Services\BusinessDayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::query()->create([
        'slug' => 'bizday',
        'data' => ['name' => 'Biz Day'],
    ]);

    $this->hotel = Hotel::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Hotel',
        'currency' => 'XAF',
        'timezone' => 'UTC',
        'check_in_time' => '14:00:00',
        'check_out_time' => '12:00:00',
        'business_day_start_time' => '08:00:00',
    ]);
});

it('resolves previous day before the start time', function () {
    $service = new BusinessDayService;

    $when = Carbon::parse('2026-01-02 07:59:00', 'UTC');
    $businessDate = $service->resolveBusinessDate($this->hotel, $when);

    expect($businessDate->toDateString())->toBe('2026-01-01');
});

it('keeps the same day after the start time', function () {
    $service = new BusinessDayService;

    $when = Carbon::parse('2026-01-02 08:00:01', 'UTC');
    $businessDate = $service->resolveBusinessDate($this->hotel, $when);

    expect($businessDate->toDateString())->toBe('2026-01-02');
});

it('handles a late start time correctly', function () {
    $this->hotel->forceFill(['business_day_start_time' => '19:00:00'])->save();
    $service = new BusinessDayService;

    $when = Carbon::parse('2026-01-02 18:59:00', 'UTC');
    $businessDate = $service->resolveBusinessDate($this->hotel, $when);

    expect($businessDate->toDateString())->toBe('2026-01-01');
});
