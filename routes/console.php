<?php

use App\Models\Hotel;
use App\Services\BusinessDayService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('business-day:backfill {--hotel=} {--from=} {--to=}', function () {
    $hotelId = $this->option('hotel');
    $fromInput = $this->option('from');
    $toInput = $this->option('to');

    if (! $hotelId) {
        $this->error('The --hotel option is required.');

        return 1;
    }

    if (! $fromInput || ! $toInput) {
        $this->error('Both --from and --to options are required (YYYY-MM-DD).');

        return 1;
    }

    $hotel = Hotel::query()->findOrFail($hotelId);

    $from = Carbon::parse($fromInput)->startOfDay();
    $to = Carbon::parse($toInput)->endOfDay();

    $service = new BusinessDayService;
    $tables = [
        'payments' => 'created_at',
        'folio_items' => 'created_at',
        'cash_sessions' => 'created_at',
        'invoices' => 'created_at',
    ];

    foreach ($tables as $table => $timestamp) {
        $this->info("Backfilling business_date on {$table}...");
        \Illuminate\Support\Facades\DB::table($table)
            ->where('tenant_id', $hotel->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->whereNull('business_date')
            ->whereBetween($timestamp, [$from, $to])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $timestamp, $hotel, $service): void {
                foreach ($rows as $row) {
                    $value = $row->{$timestamp} ?? null;
                    if (! $value) {
                        continue;
                    }

                    $businessDate = $service->resolveBusinessDate($hotel, Carbon::parse($value));
                    \Illuminate\Support\Facades\DB::table($table)
                        ->where('id', $row->id)
                        ->update(['business_date' => $businessDate->toDateString()]);
                }
            });
    }

    $this->info('Business day backfill complete.');
})->purpose('Backfill business_date for a hotel over a date range');
