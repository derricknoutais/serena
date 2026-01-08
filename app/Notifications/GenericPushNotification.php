<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class GenericPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public ?string $icon,
        public ?string $badge,
        public ?string $tag,
        public string $tenantId,
        public ?int $hotelId,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $baseTag = $this->tag ?? sprintf('tenant-%s', $this->tenantId);
        $uniqueTag = sprintf('%s-%s', $baseTag, (string) Str::uuid());

        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon($this->icon ?? '/icons/icon-192.png')
            ->badge($this->badge ?? '/icons/badge-192.png')
            ->tag($uniqueTag)
            ->data([
                'url' => $this->url,
                'tenant_id' => $this->tenantId,
                'hotel_id' => $this->hotelId,
            ]);
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->tenantId !== '';
    }
}
