<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('offers')
            ->where(function ($query): void {
                $query->whereNull('time_rule')
                    ->orWhere('time_rule', '');
            })
            ->where(function ($query): void {
                $query->whereNotNull('fixed_duration_hours')
                    ->orWhereNotNull('check_in_from')
                    ->orWhereNotNull('valid_days_of_week');
            })
            ->orderBy('id')
            ->chunkById(100, function ($offers): void {
                foreach ($offers as $offer) {
                    $timeRule = null;
                    $timeConfig = null;

                    if ($offer->fixed_duration_hours !== null) {
                        $timeRule = 'rolling';
                        $timeConfig = [
                            'duration_minutes' => (int) $offer->fixed_duration_hours * 60,
                        ];
                    } elseif (
                        $offer->valid_days_of_week !== null
                        && $offer->check_in_from !== null
                        && $offer->check_out_until !== null
                    ) {
                        $timeRule = 'weekend_window';

                        $allowedWeekdays = json_decode($offer->valid_days_of_week, true);

                        if (! is_array($allowedWeekdays)) {
                            $allowedWeekdays = null;
                        }

                        $timeConfig = [
                            'checkin' => [
                                'allowed_weekdays' => $allowedWeekdays,
                                'start_time' => substr($offer->check_in_from, 0, 5),
                            ],
                            'checkout' => [
                                'time' => substr($offer->check_out_until, 0, 5),
                                'max_days_after_checkin' => 2,
                            ],
                        ];
                    } elseif ($offer->check_in_from !== null && $offer->check_out_until !== null) {
                        $timeRule = 'fixed_window';
                        $timeConfig = [
                            'start_time' => substr($offer->check_in_from, 0, 5),
                            'end_time' => substr($offer->check_out_until, 0, 5),
                        ];
                    }

                    if ($timeRule === null || $timeConfig === null) {
                        continue;
                    }

                    DB::table('offers')
                        ->where('id', $offer->id)
                        ->update([
                            'time_rule' => $timeRule,
                            'time_config' => json_encode($timeConfig),
                        ]);
                }
            });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('offers', function (Blueprint $table): void {
            $table->dropColumn([
                'fixed_duration_hours',
                'check_in_from',
                'check_out_until',
                'valid_days_of_week',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('offers', function (Blueprint $table): void {
            $table->integer('fixed_duration_hours')->nullable();
            $table->time('check_in_from')->nullable();
            $table->time('check_out_until')->nullable();
            $table->json('valid_days_of_week')->nullable();
        });
    }
};
