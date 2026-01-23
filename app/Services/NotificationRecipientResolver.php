<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\NotificationEventCatalog;
use Illuminate\Database\Eloquent\Collection;

class NotificationRecipientResolver
{
    /**
     * @return Collection<int, User>
     */
    public function resolve(string $eventKey, string $tenantId, ?int $hotelId = null): Collection
    {
        $roles = $this->resolveRolesForEvent($eventKey, $tenantId, $hotelId);

        return $this->resolveByRoles($roles, $tenantId, $hotelId);
    }

    /**
     * @param  list<string>  $roles
     * @return Collection<int, User>
     */
    public function resolveByRoles(array $roles, string $tenantId, ?int $hotelId = null): Collection
    {
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
    public function resolveRolesForEvent(string $eventKey, string $tenantId, ?int $hotelId = null): array
    {
        $preference = $this->preferenceForEvent($eventKey, $tenantId, $hotelId);
        $roles = $preference?->roles ?? null;

        if (is_array($roles) && $roles !== []) {
            return $roles;
        }

        return NotificationEventCatalog::defaultRoles($eventKey);
    }

    /**
     * @return list<string>
     */
    public function resolveChannelsForEvent(string $eventKey, string $tenantId, ?int $hotelId = null): array
    {
        $preference = $this->preferenceForEvent($eventKey, $tenantId, $hotelId);
        $channels = $preference?->channels ?? null;

        if (is_array($channels) && $channels !== []) {
            return $channels;
        }

        return NotificationEventCatalog::defaultChannels($eventKey);
    }

    /**
     * @return list<string>
     */
    private function rolesForEvent(string $eventKey): array
    {
        $ownerManager = ['owner', 'manager'];
        $receptionOps = ['receptionist'];
        $housekeeping = ['housekeeping'];

        return NotificationEventCatalog::defaultRoles($eventKey);
    }

    private function preferenceForEvent(
        string $eventKey,
        string $tenantId,
        ?int $hotelId,
    ): ?NotificationPreference {
        $query = NotificationPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('event_key', $eventKey);

        if ($hotelId !== null) {
            $preference = (clone $query)->where('hotel_id', $hotelId)->first();
            if ($preference) {
                return $preference;
            }
        }

        return $query->whereNull('hotel_id')->first();
    }
}
