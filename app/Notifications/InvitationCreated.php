<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class InvitationCreated extends Notification
{
    use Queueable;

    public function __construct(
        private Invitation $invitation,
        private string $token,
        private ?string $invitedBy = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $tenantName = $this->invitation->tenant->name ?? $this->invitation->tenant_id;
        $acceptUrl = route('invitations.accept.show', [
            'token' => $this->token,
            'email' => $this->invitation->email,
        ], absolute: true);

        return (new MailMessage)
            ->subject(sprintf('Invitation a rejoindre %s', $tenantName))
            ->greeting(sprintf('Bonjour%s,', $this->invitedBy ? ' de la part de '.$this->invitedBy : ''))
            ->line(sprintf('Vous avez ete invite a rejoindre %s.', $tenantName))
            ->action("Accepter l'invitation", $acceptUrl)
            ->line('Ce lien est personnel. Si vous ne vous attendiez pas a cette invitation, vous pouvez ignorer ce message.')
            ->salutation(Str::finish('A bientot', '.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
