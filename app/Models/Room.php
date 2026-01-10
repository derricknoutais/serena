<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_AVAILABLE = 'active';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_OUT_OF_ORDER = 'out_of_order';

    public const HK_STATUS_DIRTY = 'dirty';

    public const HK_STATUS_CLEANING = 'cleaning';

    public const HK_STATUS_AWAITING_INSPECTION = 'awaiting_inspection';

    public const HK_STATUS_INSPECTED = 'inspected';

    public const HK_STATUS_REDO = 'redo';

    public const HK_STATUS_IN_USE = 'in_use';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'room_type_id',
        'number',
        'floor',
        'status',
        'hk_status',
        'block_sale_after_checkout',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'block_sale_after_checkout' => 'boolean',
        ];
    }

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class);
    }

    public function housekeepingTasks(): HasMany
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    public function scopeOpenMaintenanceTickets(Builder $query): Builder
    {
        return $query->whereHas('maintenanceTickets', function (Builder $ticketQuery): void {
            $ticketQuery->whereIn('status', [
                MaintenanceTicket::STATUS_OPEN,
                MaintenanceTicket::STATUS_IN_PROGRESS,
            ]);
        });
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [self::STATUS_OUT_OF_ORDER, 'inactive'])
            ->where('hk_status', self::HK_STATUS_INSPECTED)
            ->where(function (Builder $roomQuery): void {
                $roomQuery
                    ->where('block_sale_after_checkout', false)
                    ->orWhereNull('block_sale_after_checkout');
            })
            ->whereDoesntHave('maintenanceTickets', function (Builder $ticketQuery): void {
                $ticketQuery->whereIn('status', [
                    MaintenanceTicket::STATUS_OPEN,
                    MaintenanceTicket::STATUS_IN_PROGRESS,
                ])->where('blocks_sale', true);
            })
            ->whereDoesntHave('reservations', function (Builder $reservationQuery): void {
                $reservationQuery
                    ->where('status', Reservation::STATUS_IN_HOUSE)
                    ->whereNull('actual_check_out_at');
            });
    }

    public function isSellable(): bool
    {
        if ($this->block_sale_after_checkout) {
            return false;
        }

        if (in_array($this->status, [self::STATUS_OUT_OF_ORDER, 'inactive'], true)) {
            return false;
        }

        if ($this->hk_status !== self::HK_STATUS_INSPECTED) {
            return false;
        }

        if ($this->isOccupiedNow()) {
            return false;
        }

        if ($this->relationLoaded('maintenanceTickets')) {
            return $this->maintenanceTickets
                ->filter(fn (MaintenanceTicket $ticket): bool => in_array(
                    $ticket->status,
                    [MaintenanceTicket::STATUS_OPEN, MaintenanceTicket::STATUS_IN_PROGRESS],
                    true,
                ))
                ->where('blocks_sale', true)
                ->isEmpty();
        }

        return ! $this->maintenanceTickets()
            ->whereIn('status', [MaintenanceTicket::STATUS_OPEN, MaintenanceTicket::STATUS_IN_PROGRESS])
            ->where('blocks_sale', true)
            ->exists();
    }

    public function isBlockedByMaintenance(): bool
    {
        return ! $this->isSellable();
    }

    public function isOccupiedNow(): bool
    {
        return $this->reservations()
            ->where('status', Reservation::STATUS_IN_HOUSE)
            ->whereNull('actual_check_out_at')
            ->exists();
    }

    public function isBlockedAfterCheckout(): bool
    {
        return (bool) $this->block_sale_after_checkout;
    }
}
