<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DevResetRoomboard extends Command
{
    protected $signature = 'dev:reset-roomboard {--tenant= : Tenant UUID to scope the reset} {--hotel= : Hotel ID to scope the reset}';

    protected $description = 'Closes active reservations and resets room housekeeping status for a clean roomboard (dev only).';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('This command is only available in non-production environments.');

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant');
        $hotelId = $this->option('hotel');
        $now = Carbon::now();

        $result = DB::transaction(function () use ($tenantId, $hotelId, $now): array {
            $reservationQuery = Reservation::query()
                ->whereIn('status', [
                    Reservation::STATUS_CONFIRMED,
                    Reservation::STATUS_IN_HOUSE,
                ]);

            if ($tenantId) {
                $reservationQuery->where('tenant_id', $tenantId);
            }

            if ($hotelId) {
                $reservationQuery->where('hotel_id', $hotelId);
            }

            $reservationsUpdated = $reservationQuery->update([
                'status' => Reservation::STATUS_CHECKED_OUT,
                'actual_check_out_at' => $now,
            ]);

            $roomsQuery = Room::query();

            if ($tenantId) {
                $roomsQuery->where('tenant_id', $tenantId);
            }

            if ($hotelId) {
                $roomsQuery->where('hotel_id', $hotelId);
            }

            $roomsUpdated = (clone $roomsQuery)->update([
                'hk_status' => Room::HK_STATUS_INSPECTED,
            ]);

            $roomsStatusUpdated = (clone $roomsQuery)
                ->whereIn('status', [Room::STATUS_IN_USE, Room::STATUS_OCCUPIED])
                ->update([
                    'status' => Room::STATUS_AVAILABLE,
                ]);

            return [
                'reservations' => $reservationsUpdated,
                'rooms' => $roomsUpdated,
                'rooms_status' => $roomsStatusUpdated,
            ];
        });

        $this->info(sprintf(
            'Reset done: %d reservations closed, %d rooms set to inspected, %d rooms marked available.',
            $result['reservations'],
            $result['rooms'],
            $result['rooms_status'],
        ));

        return self::SUCCESS;
    }
}
