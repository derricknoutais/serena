<?php

namespace App\Policies;

use App\Models\MaintenanceTicket;
use App\Models\User;

class MaintenanceTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasActiveHotel($user) && $user->hasPermissionTo('maintenance_tickets.view');
    }

    public function view(User $user, MaintenanceTicket $maintenanceTicket): bool
    {
        return $this->belongsToUserContext($user, $maintenanceTicket)
            && $user->hasPermissionTo('maintenance_tickets.view');
    }

    public function create(User $user): bool
    {
        return $this->hasActiveHotel($user)
            && $user->hasAnyPermission(['maintenance_tickets.create', 'maintenance.tickets.create']);
    }

    public function update(User $user, MaintenanceTicket $maintenanceTicket): bool
    {
        if (! $this->belongsToUserContext($user, $maintenanceTicket)) {
            return false;
        }

        return $user->hasAnyPermission(['maintenance_tickets.update', 'maintenance.tickets.update']);
    }

    public function close(User $user, MaintenanceTicket $maintenanceTicket): bool
    {
        if (! $this->belongsToUserContext($user, $maintenanceTicket)) {
            return false;
        }

        return $user->hasAnyPermission(['maintenance_tickets.close', 'maintenance.tickets.close']);
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
