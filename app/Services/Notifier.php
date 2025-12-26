<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Notifier
{
    public function __construct(
        private readonly NotificationRecipientResolver $recipientResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     */
    public function notify(string $eventKey, ?int $hotelId, array $payload, array $options = []): void
    {
        $tenantId = $options['tenant_id'] ?? Auth::user()?->tenant_id ?? Arr::get($payload, 'tenant_id');

        if (! $tenantId) {
            return;
        }

        $meta = [
            'tenant_id' => $tenantId,
            'hotel_id' => $hotelId,
            'cta_route' => $options['cta_route'] ?? null,
            'cta_params' => $options['cta_params'] ?? [],
            'cta_url' => $this->ctaUrl($options['cta_route'] ?? null, $options['cta_params'] ?? []),
        ];

        $formatted = $this->format($eventKey, $payload);
        $recipients = $this->recipientResolver->resolve($eventKey, $tenantId, $hotelId);

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            $recipient->notify(
                new AppNotification(
                    $eventKey,
                    $formatted['title'],
                    $formatted['message'],
                    [
                        ...$payload,
                        'tenant_id' => $tenantId,
                        'hotel_id' => $hotelId,
                    ],
                    $meta,
                )
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{title:string, message:string}
     */
    private function format(string $eventKey, array $payload): array
    {
        $currency = $payload['currency'] ?? 'XAF';

        return match ($eventKey) {
            'cash_session.opened' => [
                'title' => 'Caisse ouverte',
                'message' => sprintf(
                    'Session %s ouverte par %s',
                    $payload['session_code'] ?? '#',
                    $payload['user_name'] ?? 'Utilisateur'
                ),
            ],
            'cash_session.closed' => [
                'title' => 'Caisse fermée',
                'message' => $payload['difference'] !== null
                    ? sprintf(
                        'Écart de caisse : %s %s',
                        $this->formatAmount((float) $payload['difference']),
                        $currency
                    )
                    : 'Session caisse fermée',
            ],
            'business_day.closed' => [
                'title' => 'Journée clôturée',
                'message' => sprintf('Journée business %s clôturée', $payload['business_date'] ?? ''),
            ],
            'business_day.reopened' => [
                'title' => 'Journée ré-ouverte',
                'message' => sprintf('Journée business %s ré-ouverte', $payload['business_date'] ?? ''),
            ],
            'reservation.created' => [
                'title' => 'Nouvelle réservation',
                'message' => sprintf(
                    'Réservation %s créée pour %s',
                    $payload['reservation_code'] ?? '',
                    $payload['guest_name'] ?? 'client'
                ),
            ],
            'reservation.updated' => [
                'title' => 'Réservation mise à jour',
                'message' => sprintf(
                    'Réservation %s mise à jour',
                    $payload['reservation_code'] ?? ''
                ),
            ],
            'reservation.checked_in' => [
                'title' => 'Check-in effectué',
                'message' => sprintf(
                    '%s est arrivé (réservation %s)',
                    $payload['guest_name'] ?? 'Client',
                    $payload['reservation_code'] ?? ''
                ),
            ],
            'reservation.checked_out' => [
                'title' => 'Check-out effectué',
                'message' => sprintf(
                    '%s est parti (réservation %s)',
                    $payload['guest_name'] ?? 'Client',
                    $payload['reservation_code'] ?? ''
                ),
            ],
            'reservation.conflict_detected' => [
                'title' => 'Conflit de réservation',
                'message' => sprintf(
                    'Chambre %s déjà réservée (%s)',
                    $payload['room_number'] ?? 'N/A',
                    $payload['existing_code'] ?? ''
                ),
            ],
            'room.sold_but_dirty' => [
                'title' => 'Chambre vendue mais sale',
                'message' => sprintf('Chambre %s vendue alors qu’elle est sale', $payload['room_number'] ?? ''),
            ],
            'room.hk_status_updated' => [
                'title' => 'Statut ménage mis à jour',
                'message' => sprintf(
                    'Chambre %s : %s → %s',
                    $payload['room_number'] ?? '',
                    $payload['from_status'] ?? '',
                    $payload['to_status'] ?? ''
                ),
            ],
            'folio.balance_remaining_on_checkout' => [
                'title' => 'Solde restant au départ',
                'message' => sprintf(
                    'Solde de %s %s sur la réservation %s',
                    $this->formatAmount((float) ($payload['balance'] ?? 0)),
                    $currency,
                    $payload['reservation_code'] ?? ''
                ),
            ],
            default => [
                'title' => Str::headline($eventKey),
                'message' => $payload['message'] ?? 'Nouvelle notification',
            ],
        };
    }

    private function ctaUrl(?string $route, array $params = []): ?string
    {
        if (! $route) {
            return null;
        }

        try {
            return URL::route($route, $params);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 0, ',', ' ');
    }
}
