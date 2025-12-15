<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

class TenantDatabaseChannel extends DatabaseChannel
{
    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(mixed $notifiable, Notification $notification): array
    {
        $data = $notification->toDatabase($notifiable);
        $tenantId = $data['tenant_id'] ?? null;
        $hotelId = $data['hotel_id'] ?? null;

        return [
            'id' => $notification->id,
            'type' => $notification::class,
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => $notifiable->getKey(),
            'data' => $data,
            'read_at' => null,
            'tenant_id' => $tenantId,
            'hotel_id' => $hotelId,
        ];
    }
}
