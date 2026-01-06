<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HousekeepingChecklist;
use App\Models\User;

class HousekeepingChecklistPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, HousekeepingChecklist $housekeepingChecklist): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, HousekeepingChecklist $housekeepingChecklist): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, HousekeepingChecklist $housekeepingChecklist): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRole(['owner', 'manager', 'superadmin']);
    }
}
