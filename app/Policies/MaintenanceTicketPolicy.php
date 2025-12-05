<?php

namespace App\Policies;

use App\Models\MaintenanceTicket;
use App\Models\User;

class MaintenanceTicketPolicy
{
    private const REPORT_ROLES = [
        'owner',
        'manager',
        'receptionist',
        'housekeeping',
        'maintenance',
        'superadmin',
    ];

    private const MANAGE_ROLES = [
        'owner',
        'manager',
        'maintenance',
        'superadmin',
    ];

    public function viewAny(User $user): bool
    {
        return $this->hasActiveHotel($user);
    }

    public function view(User $user, MaintenanceTicket $maintenanceTicket): bool
    {
        return $this->belongsToUserContext($user, $maintenanceTicket);
    }

    public function create(User $user): bool
    {
        return $this->hasActiveHotel($user)
            && $user->hasRole(self::REPORT_ROLES);
    }

    public function update(User $user, MaintenanceTicket $maintenanceTicket): bool
    {
        if (! $this->belongsToUserContext($user, $maintenanceTicket)) {
            return false;
        }

        return $user->hasRole(array_unique(
            array_merge(self::REPORT_ROLES, self::MANAGE_ROLES),
        ));
    }

    public function delete(User $user, MaintenanceTicket $maintenanceTicket): bool
    {
        return false;
    }

    public function restore(User $user, MaintenanceTicket $maintenanceTicket): bool
    {
        return false;
    }

    public function forceDelete(User $user, MaintenanceTicket $maintenanceTicket): bool
    {
        return false;
    }

    private function belongsToUserContext(User $user, MaintenanceTicket $ticket): bool
    {
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        return $ticket->tenant_id === $user->tenant_id
            && (int) $ticket->hotel_id === $hotelId;
    }

    private function hasActiveHotel(User $user): bool
    {
        return (bool) ($user->active_hotel_id ?? $user->hotel_id);
    }
}
