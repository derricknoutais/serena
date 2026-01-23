<?php

namespace App\Support;

class NotificationEventCatalog
{
    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNEL_PUSH = 'push';

    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     roles: list<string>,
     *     channels: list<string>
     * }>
     */
    public static function all(): array
    {
        $ownerManager = ['owner', 'manager'];
        $receptionOps = ['receptionist'];
        $housekeeping = ['housekeeping'];

        return [
            [
                'key' => 'reservation.created',
                'label' => 'Nouvelle réservation',
                'description' => 'Lorsqu’une réservation est créée.',
                'roles' => array_merge($ownerManager, $receptionOps),
                'channels' => [self::CHANNEL_IN_APP, self::CHANNEL_PUSH],
            ],
            [
                'key' => 'reservation.updated',
                'label' => 'Réservation mise à jour',
                'description' => 'Lorsqu’une réservation est mise à jour.',
                'roles' => array_merge($ownerManager, $receptionOps),
                'channels' => [self::CHANNEL_IN_APP, self::CHANNEL_PUSH],
            ],
            [
                'key' => 'reservation.extended',
                'label' => 'Prolongation de séjour',
                'description' => 'Lorsqu’un séjour est prolongé.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_PUSH],
            ],
            [
                'key' => 'reservation.room_moved',
                'label' => 'Changement de chambre',
                'description' => 'Lorsqu’une réservation change de chambre.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_PUSH],
            ],
            [
                'key' => 'reservation.checked_in',
                'label' => 'Check-in effectué',
                'description' => 'Lorsqu’un client arrive.',
                'roles' => array_merge($ownerManager, $receptionOps),
                'channels' => [self::CHANNEL_IN_APP],
            ],
            [
                'key' => 'reservation.checked_out',
                'label' => 'Check-out effectué',
                'description' => 'Lorsqu’un client quitte l’hôtel.',
                'roles' => array_merge($ownerManager, $receptionOps),
                'channels' => [self::CHANNEL_IN_APP, self::CHANNEL_PUSH],
            ],
            [
                'key' => 'reservation.conflict_detected',
                'label' => 'Conflit de réservation',
                'description' => 'Lorsqu’un conflit de réservation est détecté.',
                'roles' => array_merge($ownerManager, $receptionOps),
                'channels' => [self::CHANNEL_IN_APP],
            ],
            [
                'key' => 'room.sold_but_dirty',
                'label' => 'Chambre vendue mais sale',
                'description' => 'Lorsqu’une chambre est vendue alors qu’elle est sale.',
                'roles' => array_merge($ownerManager, $receptionOps, $housekeeping),
                'channels' => [self::CHANNEL_IN_APP],
            ],
            [
                'key' => 'room.hk_status_updated',
                'label' => 'Statut ménage mis à jour',
                'description' => 'Lorsqu’un statut ménage change.',
                'roles' => array_merge($ownerManager, $receptionOps, $housekeeping),
                'channels' => [self::CHANNEL_IN_APP, self::CHANNEL_PUSH],
            ],
            [
                'key' => 'cash_session.opened',
                'label' => 'Caisse ouverte',
                'description' => 'Lorsqu’une session de caisse est ouverte.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_IN_APP],
            ],
            [
                'key' => 'cash_session.closed',
                'label' => 'Caisse fermée',
                'description' => 'Lorsqu’une session de caisse est fermée.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_IN_APP],
            ],
            [
                'key' => 'business_day.closed',
                'label' => 'Journée clôturée',
                'description' => 'Lorsqu’une journée business est clôturée.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_IN_APP],
            ],
            [
                'key' => 'business_day.reopened',
                'label' => 'Journée ré-ouverte',
                'description' => 'Lorsqu’une journée business est ré-ouverte.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_IN_APP],
            ],
            [
                'key' => 'folio.balance_remaining_on_checkout',
                'label' => 'Solde restant au départ',
                'description' => 'Lorsqu’un solde reste après un check-out.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_IN_APP],
            ],
            [
                'key' => 'pos.sale',
                'label' => 'Vente POS',
                'description' => 'Lorsqu’une vente comptoir est enregistrée.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_PUSH],
            ],
            [
                'key' => 'pos.room_sale',
                'label' => 'Vente POS chambre',
                'description' => 'Lorsqu’une vente POS est ajoutée à une chambre.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_PUSH],
            ],
            [
                'key' => 'payment.recorded',
                'label' => 'Paiement enregistré',
                'description' => 'Lorsqu’un paiement est enregistré.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_PUSH],
            ],
            [
                'key' => 'payment.updated',
                'label' => 'Paiement mis à jour',
                'description' => 'Lorsqu’un paiement est modifié.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_PUSH],
            ],
            [
                'key' => 'payment.deleted',
                'label' => 'Paiement supprimé',
                'description' => 'Lorsqu’un paiement est supprimé.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_PUSH],
            ],
            [
                'key' => 'payment.voided',
                'label' => 'Paiement annulé',
                'description' => 'Lorsqu’un paiement est annulé.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_PUSH],
            ],
            [
                'key' => 'payment.refunded',
                'label' => 'Paiement remboursé',
                'description' => 'Lorsqu’un paiement est remboursé.',
                'roles' => $ownerManager,
                'channels' => [self::CHANNEL_PUSH],
            ],
        ];
    }

    public static function defaultsFor(string $eventKey): ?array
    {
        foreach (self::all() as $item) {
            if ($item['key'] === $eventKey) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function defaultRoles(string $eventKey): array
    {
        return self::defaultsFor($eventKey)['roles'] ?? ['owner', 'manager'];
    }

    /**
     * @return list<string>
     */
    public static function defaultChannels(string $eventKey): array
    {
        return self::defaultsFor($eventKey)['channels'] ?? [self::CHANNEL_IN_APP];
    }
}
