<?php

use App\Models\Hotel;
use App\Models\HotelDayClosure;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessDayService;
use App\Services\NightAuditLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::query()->create([
        'slug' => 'night-lock',
        'data' => ['name' => 'Night Lock'],
    ]);

    $this->hotel = Hotel::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Audit Hotel',
        'currency' => 'XAF',
        'timezone' => 'UTC',
        'check_in_time' => '14:00:00',
        'check_out_time' => '12:00:00',
        'business_day_start_time' => '08:00:00',
    ]);

    $this->service = new NightAuditLockService(new BusinessDayService);
});

it('blocks business day changes when closed', function () {
    $businessDate = Carbon::parse('2026-02-01')->startOfDay();

    $this->service->closeDay($this->hotel, $businessDate, [], null);

    $closure = HotelDayClosure::query()->where('hotel_id', $this->hotel->id)->first();
    expect($closure)->not->toBeNull();
    expect($closure->status)->toBe(HotelDayClosure::STATUS_CLOSED);
    expect($closure->business_date->toDateString())->toBe($businessDate->toDateString());
    $queriedClosure = $this->service->closureFor($this->hotel, $businessDate);
    expect($queriedClosure)->not->toBeNull();
    expect($this->service->isClosed($this->hotel, $businessDate))->toBeTrue();

    expect(fn () => $this->service->assertBusinessDateOpen($this->hotel, $businessDate, null))->toThrow(HttpException::class);
});

it('allows override when the user has the required role', function () {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
    $user->assignRole('owner');

    $businessDate = Carbon::parse('2026-02-01')->startOfDay();
    $this->service->closeDay($this->hotel, $businessDate, [], null);

    $this->service->assertBusinessDateOpen($this->hotel, $businessDate, $user, true);

    expect(true)->toBeTrue();
});
