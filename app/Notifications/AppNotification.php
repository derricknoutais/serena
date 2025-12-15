<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $eventKey,
        public string $title,
        public string $message,
        public array $payload = [],
        public array $meta = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['tenant_database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'event' => $this->eventKey,
            'title' => $this->title,
            'message' => $this->message,
            'payload' => $this->payload,
            ...$this->meta,
        ];
    }
}
