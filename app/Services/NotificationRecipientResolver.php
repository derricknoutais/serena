<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationRecipientResolver
{
    /**
     * @return Collection<int, User>
     */
    public function resolve(string $eventKey, string $tenantId, ?int $hotelId = null): Collection
    {
        $roles = $this->rolesForEvent($eventKey);

        $query = User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', function ($q) use ($roles): void {
                $q->whereIn('name', $roles);
            });

        if ($hotelId !== null) {
            $query->where(function ($q) use ($hotelId): void {
                $q->where('active_hotel_id', $hotelId)
                    ->orWhereHas('hotels', fn ($hq) => $hq->where('hotel_id', $hotelId));
            });
        }

        return $query->get();
    }

    /**
     * @return list<string>
     */
    private function rolesForEvent(string $eventKey): array
    {
        $ownerManager = ['owner', 'manager'];
        $receptionOps = ['receptionist'];
        $housekeeping = ['housekeeping'];

        return match ($eventKey) {
            'cash_session.opened',
            'cash_session.closed',
            'business_day.closed',
            'business_day.reopened',
            'folio.balance_remaining_on_checkout' => $ownerManager,
            'reservation.created',
            'reservation.updated' => array_merge($ownerManager, $receptionOps),
            'reservation.checked_in',
            'reservation.checked_out',
            'reservation.conflict_detected' => array_merge($ownerManager, $receptionOps),
            'room.sold_but_dirty' => array_merge($ownerManager, $receptionOps, $housekeeping),
            'room.hk_status_updated' => array_merge($ownerManager, $receptionOps, $housekeeping),
            default => $ownerManager,
        };
    }
}
