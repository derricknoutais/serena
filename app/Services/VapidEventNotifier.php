<?php

namespace App\Services;

use App\Notifications\GenericPushNotification;

class VapidEventNotifier
{
    public function __construct(
        private readonly NotificationRecipientResolver $recipientResolver,
        private readonly PushNotificationSender $pushNotificationSender,
    ) {}

    public function notifyOwnersAndManagers(
        string $eventKey,
        string $tenantId,
        ?int $hotelId,
        string $title,
        string $body,
        string $url,
        ?string $tag = null,
    ): void {
        $channels = $this->recipientResolver->resolveChannelsForEvent($eventKey, $tenantId, $hotelId);
        if (! in_array('push', $channels, true)) {
            return;
        }

        $roles = $this->recipientResolver->resolveRolesForEvent($eventKey, $tenantId, $hotelId);
        $recipients = $this->recipientResolver->resolveByRoles($roles, $tenantId, $hotelId);
        if ($recipients->isEmpty() && $hotelId !== null) {
            $recipients = $this->recipientResolver->resolveByRoles($roles, $tenantId);
        }

        $notification = new GenericPushNotification(
            title: $title,
            body: $body,
            url: $url,
            icon: null,
            badge: null,
            tag: $tag,
            tenantId: $tenantId,
            hotelId: $hotelId,
        );

        if ($recipients->isEmpty()) {
            $this->pushNotificationSender->send(
                tenantId: $tenantId,
                notification: $notification,
                roles: $roles,
            );

            return;
        }

        $this->pushNotificationSender->send(
            tenantId: $tenantId,
            notification: $notification,
            userIds: $recipients->pluck('id')->all(),
        );
    }
}
