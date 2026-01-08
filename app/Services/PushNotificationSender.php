<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\GenericPushNotification;
use Illuminate\Support\Facades\Notification;

class PushNotificationSender
{
    /**
     * @param  array<int, string>  $roles
     * @param  array<int, int>  $userIds
     */
    public function send(
        string $tenantId,
        GenericPushNotification $notification,
        array $roles = [],
        array $userIds = [],
    ): void {
        if ($roles === [] && $userIds === []) {
            return;
        }

        $query = User::query()->where('tenant_id', $tenantId);

        if ($userIds !== []) {
            $query->whereIn('id', $userIds);
        }

        if ($roles !== []) {
            $query->whereHas('roles', function ($roleQuery) use ($roles): void {
                $roleQuery->whereIn('name', $roles);
            });
        }

        $recipients = $query->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }
}
