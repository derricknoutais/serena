<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\BarOrder;
use App\Models\CashSession;
use App\Models\Folio;
use App\Models\MaintenanceIntervention;
use App\Models\MaintenanceTicket;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\StockInventory;
use App\Models\StockPurchase;
use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ActivityFormatter
{
    /**
     * @return array{
     *     module_label_fr: string,
     *     action_label_fr: string,
     *     subject_label_fr: string,
     *     sentence_fr: string,
     *     meta: list<string>,
     *     action_key: string
     * }
     */
    public static function format(Activity $activity): array
    {
        $moduleLabel = self::moduleLabel($activity->log_name);
        $subjectLabel = self::subjectLabel($activity->subject, $activity);
        $actionKey = self::normalizeAction($activity);
        $actionLabel = self::actionLabel($actionKey, $activity);
        $sentence = self::sentenceFor($activity, $actionKey, $subjectLabel, $actionLabel);
        $meta = self::metaFor($activity, $actionKey);

        return [
            'module_label_fr' => $moduleLabel,
            'action_label_fr' => $actionLabel,
            'subject_label_fr' => $subjectLabel,
            'sentence_fr' => $sentence,
            'meta' => $meta,
            'action_key' => $actionKey,
        ];
    }

    public static function moduleLabel(?string $logName): string
    {
        if (! $logName) {
            return 'Activités';
        }

        return self::moduleMap()[$logName] ?? ucfirst($logName);
    }

    /**
     * @return array<string, string>
     */
    public static function moduleMap(): array
    {
        return [
            'reservation' => 'Réservations',
            'room' => 'Chambres',
            'maintenance' => 'Maintenance',
            'stock' => 'Stock',
            'bar' => 'Bar',
            'cash' => 'Caisse',
            'billing' => 'Facturation',
            'folio' => 'Facturation',
            'payment' => 'Facturation',
        ];
    }

    public static function normalizeAction(Activity $activity): string
    {
        $raw = (string) ($activity->description ?? $activity->event ?? '');
        $normalized = Str::of($raw)->lower()->ascii()->replace(['-', '_'], ' ')->trim()->toString();

        $amount = self::amountValue($activity);
        if ($amount < 0) {
            return 'refunded';
        }

        $map = [
            'created' => ['created', 'create', 'cree', 'cree', 'creer'],
            'voided' => ['voided', 'void', 'annule', 'annulee', 'annuler', 'annulation'],
            'refunded' => ['refunded', 'refund', 'rembourse', 'remboursement'],
            'checked_in' => ['checked in', 'check in', 'checkin', 'checked_in'],
            'checked_out' => ['checked out', 'check out', 'checkout', 'checked_out'],
            'confirmed' => ['confirmed', 'confirme', 'confirmation'],
            'hk_updated' => ['hk updated', 'hk_update', 'menage', 'housekeeping'],
            'opened' => ['opened', 'open', 'ouvert', 'ouverte'],
            'closed' => ['closed', 'close', 'ferme', 'fermee'],
            'submitted' => ['submitted', 'soumis', 'soumise'],
            'approved' => ['approved', 'approuve', 'approuvee'],
            'rejected' => ['rejected', 'rejete', 'rejetee'],
            'paid' => ['paid', 'paye', 'payee'],
            'purchase_received' => ['purchase received', 'receptionne', 'reception'],
            'transfer_completed' => ['transfer completed', 'transfert', 'transfer'],
            'inventory_posted' => ['inventory posted', 'inventaire', 'inventory'],
            'guest_changed' => ['guest changed', 'guest_changed', 'client change', 'client modifie', 'client modifiee'],
            'offer_changed' => ['offer changed', 'offer_changed', 'offre change', 'offre modifie', 'offre modifiee'],
            'times_overridden' => ['times overridden', 'times_overridden', 'horaires modifie', 'horaires modifiee'],
            'room_moved' => ['room moved', 'room_moved', 'chambre change', 'chambre modifie', 'chambre modifiee'],
            'adjustment_added' => ['adjustment added', 'adjustment_added', 'ajustement', 'ajuste'],
            'cost_line_added' => ['cost_line_added', 'maintenance.cost_line_added', 'cost line added', 'cout ajoute', 'coût ajoute'],
            'cost_line_updated' => ['cost_line_updated', 'maintenance.cost_line_updated', 'cost line updated', 'cout modifie', 'coût modifie'],
            'cost_line_removed' => ['cost_line_removed', 'maintenance.cost_line_removed', 'cost line removed', 'cout supprime', 'coût supprime'],
            'stock_consumed' => ['stock_consumed_for_intervention', 'maintenance.stock_consumed_for_intervention', 'stock consumed', 'piece consommee', 'pièce consommée'],
        ];

        foreach ($map as $key => $candidates) {
            foreach ($candidates as $candidate) {
                if (str_contains($normalized, $candidate)) {
                    return $key;
                }
            }
        }

        $slug = Str::slug($raw, '_');
        if ($slug !== '') {
            return $slug;
        }

        return 'updated';
    }

    public static function actionLabel(string $actionKey, Activity $activity): string
    {
        $module = $activity->log_name ?? '';
        $isBilling = in_array($module, ['payment', 'billing', 'folio'], true)
            || $activity->subject instanceof Payment
            || $activity->subject instanceof Folio;

        if ($isBilling) {
            return match ($actionKey) {
                'voided' => 'Paiement annulé',
                'refunded' => 'Remboursement',
                'created' => 'Paiement enregistré',
                'adjustment_added' => 'Ajustement de folio',
                default => 'Paiement mis à jour',
            };
        }

        return [
            'created' => 'Créé',
            'voided' => 'Annulé',
            'refunded' => 'Remboursé',
            'checked_in' => 'Check-in',
            'checked_out' => 'Check-out',
            'confirmed' => 'Confirmée',
            'cancelled' => 'Annulée',
            'no_show' => 'No-show',
            'hk_updated' => 'Ménage mis à jour',
            'opened' => 'Ouvert',
            'closed' => 'Fermé',
            'submitted' => 'Soumis',
            'approved' => 'Approuvé',
            'rejected' => 'Rejeté',
            'paid' => 'Payé',
            'purchase_received' => 'Achat réceptionné',
            'transfer_completed' => 'Transfert complété',
            'inventory_posted' => 'Inventaire posté',
            'cost_line_added' => 'Coût estimé ajouté',
            'cost_line_updated' => 'Coût estimé mis à jour',
            'cost_line_removed' => 'Coût estimé supprimé',
            'stock_consumed' => 'Pièce consommée',
        ][$actionKey] ?? Str::title(str_replace('_', ' ', $actionKey));
    }

    public static function subjectLabel(?Model $subject, Activity $activity): string
    {
        $properties = $activity->properties?->toArray() ?? [];

        if ($subject instanceof Reservation) {
            $code = $properties['reservation_code'] ?? $subject->code ?? $subject->getKey();

            return sprintf('Réservation %s', $code);
        }

        if ($subject instanceof Room) {
            $number = $properties['room_number'] ?? $subject->number ?? $subject->getKey();

            return sprintf('Chambre %s', $number);
        }

        if ($subject instanceof Payment) {
            $reference = $properties['payment_reference'] ?? $properties['reference'] ?? $subject->getKey();

            return sprintf('Paiement %s', $reference);
        }

        if ($subject instanceof Folio) {
            $code = $properties['folio_code'] ?? $subject->code ?? $subject->getKey();

            return sprintf('Folio %s', $code);
        }

        if ($subject instanceof CashSession) {
            return sprintf('Session de caisse %s', $subject->getKey());
        }

        if ($subject instanceof MaintenanceTicket) {
            $title = $subject->title ? sprintf(' – %s', $subject->title) : '';

            return sprintf('Ticket #%s%s', $subject->getKey(), $title);
        }

        if ($subject instanceof MaintenanceIntervention) {
            return sprintf('Intervention #%s', $subject->getKey());
        }

        if ($subject instanceof StockPurchase) {
            $ref = $subject->reference_no ?? $subject->getKey();

            return sprintf('Bon d’achat %s', $ref);
        }

        if ($subject instanceof StockTransfer) {
            return sprintf('Transfert %s', $subject->getKey());
        }

        if ($subject instanceof StockInventory) {
            return sprintf('Inventaire %s', $subject->getKey());
        }

        if ($subject instanceof BarOrder) {
            return sprintf('Bar %s', $subject->getKey());
        }

        $type = $activity->subject_type ?? 'Élément';
        $id = $activity->subject_id ?? '—';

        return sprintf('%s #%s', class_basename($type), $id);
    }

    public static function sentenceFor(
        Activity $activity,
        string $actionKey,
        string $subjectLabel,
        string $actionLabel,
    ): string {
        $user = $activity->causer?->name ?? 'Quelqu’un';
        $module = $activity->log_name ?? '';
        $properties = $activity->properties?->toArray() ?? [];
        $isBilling = in_array($module, ['payment', 'billing', 'folio'], true)
            || $activity->subject instanceof Payment
            || $activity->subject instanceof Folio;

        if ($isBilling) {
            return match ($actionKey) {
                'voided' => sprintf('%s a annulé un paiement.', $user),
                'refunded' => sprintf('%s a effectué un remboursement.', $user),
                'created' => sprintf('%s a enregistré un paiement.', $user),
                'adjustment_added' => sprintf('%s a ajouté un ajustement de folio.', $user),
                default => sprintf('%s a mis à jour un paiement.', $user),
            };
        }

        if ($module === 'reservation' || $activity->subject instanceof Reservation) {
            $roomNumber = $properties['room_number'] ?? null;
            $roomLabel = $roomNumber ? sprintf('Chambre %s', $roomNumber) : null;

            return match ($actionKey) {
                'confirmed' => sprintf('%s a confirmé %s.', $user, $subjectLabel),
                'checked_in' => sprintf('%s a fait le check-in de %s.', $user, $subjectLabel),
                'checked_out' => $roomLabel
                    ? sprintf('%s a fait le check-out de %s (%s).', $user, $subjectLabel, $roomLabel)
                    : sprintf('%s a fait le check-out de %s.', $user, $subjectLabel),
                'cancelled' => sprintf('%s a annulé %s.', $user, $subjectLabel),
                'no_show' => sprintf('%s a marqué %s en no-show.', $user, $subjectLabel),
                'guest_changed' => sprintf('%s a changé le client de %s.', $user, $subjectLabel),
                'offer_changed' => sprintf('%s a changé l’offre de %s.', $user, $subjectLabel),
                'times_overridden' => sprintf('%s a ajusté les dates/heures de %s.', $user, $subjectLabel),
                'room_moved' => sprintf('%s a déplacé %s.', $user, $subjectLabel),
                default => sprintf('%s a mis à jour %s.', $user, $subjectLabel),
            };
        }

        if ($module === 'room' || $activity->subject instanceof Room) {
            if ($actionKey === 'hk_updated') {
                return sprintf('%s a mis à jour le statut ménage de %s.', $user, $subjectLabel);
            }

            return sprintf('%s a mis à jour %s.', $user, $subjectLabel);
        }

        if ($module === 'cash' || $activity->subject instanceof CashSession) {
            return match ($actionKey) {
                'opened' => sprintf('%s a ouvert une session de caisse.', $user),
                'closed' => sprintf('%s a fermé une session de caisse.', $user),
                default => sprintf('%s a mis à jour une session de caisse.', $user),
            };
        }

        if ($module === 'stock') {
            return match ($actionKey) {
                'purchase_received' => sprintf('%s a réceptionné un bon d’achat.', $user),
                'transfer_completed' => sprintf('%s a effectué un transfert de stock.', $user),
                'inventory_posted' => sprintf('%s a posté un inventaire.', $user),
                default => sprintf('%s a mis à jour un mouvement de stock.', $user),
            };
        }

        return sprintf('%s — %s', $user, $actionLabel);
    }

    /**
     * @return list<string>
     */
    public static function metaFor(Activity $activity, string $actionKey): array
    {
        $properties = $activity->properties?->toArray() ?? [];
        $meta = [];

        if (in_array($activity->log_name, ['payment', 'billing', 'folio'], true) || $activity->subject instanceof Payment) {
            $meta[] = self::subjectLabel($activity->subject, $activity);
            $amount = self::amountValue($activity);
            if ($amount !== 0.0) {
                $meta[] = sprintf('Montant: %s %s', self::formatAmount(abs($amount)), self::currency($activity));
            }
            $method = $properties['payment_method'] ?? $properties['payment_method_name'] ?? null;
            if ($method) {
                $meta[] = sprintf('Méthode: %s', $method);
            }
            $reason = $properties['reason'] ?? $properties['void_reason'] ?? null;
            if ($actionKey === 'voided' && $reason) {
                $meta[] = sprintf('Raison: %s', $reason);
            }
            if ($actionKey === 'adjustment_added' && $reason) {
                $meta[] = sprintf('Raison: %s', $reason);
            }
        }

        if ($activity->log_name === 'reservation' || $activity->subject instanceof Reservation) {
            $from = $properties['from_status'] ?? null;
            $to = $properties['to_status'] ?? null;
            if ($from || $to) {
                $meta[] = sprintf('Statut: %s → %s', self::reservationStatusLabel($from), self::reservationStatusLabel($to));
            }
            $roomNumber = $properties['room_number'] ?? null;
            if ($roomNumber) {
                $meta[] = sprintf('Chambre: %s', $roomNumber);
            }
        }

        if ($activity->log_name === 'room' || $activity->subject instanceof Room) {
            if ($actionKey === 'hk_updated') {
                $fromHk = $properties['from_hk_status'] ?? null;
                $toHk = $properties['to_hk_status'] ?? null;
                if ($fromHk || $toHk) {
                    $meta[] = sprintf('Ménage: %s → %s', self::hkStatusLabel($fromHk), self::hkStatusLabel($toHk));
                } elseif ($properties['hk_status'] ?? null) {
                    $meta[] = sprintf('Nouveau statut: %s', self::hkStatusLabel($properties['hk_status']));
                }
            }
        }

        if ($activity->log_name === 'cash' || $activity->subject instanceof CashSession) {
            if (Arr::has($properties, 'opening_amount')) {
                $meta[] = sprintf('Fond: %s %s', self::formatAmount((float) $properties['opening_amount']), self::currency($activity));
            }
            if (Arr::has($properties, 'closing_amount')) {
                $meta[] = sprintf('Clôture: %s %s', self::formatAmount((float) $properties['closing_amount']), self::currency($activity));
            }
            if (Arr::has($properties, 'variance')) {
                $meta[] = sprintf('Écart: %s %s', self::formatAmount((float) $properties['variance']), self::currency($activity));
            }
        }

        if ($activity->log_name === 'stock') {
            if ($actionKey === 'purchase_received') {
                $reference = $properties['reference_no'] ?? null;
                if ($reference) {
                    $meta[] = sprintf('Référence: %s', $reference);
                }
                $location = $properties['storage_location'] ?? $properties['storage_location_name'] ?? null;
                if ($location) {
                    $meta[] = sprintf('Entrepôt: %s', $location);
                }
                $total = $properties['total_amount'] ?? $properties['subtotal_amount'] ?? null;
                if ($total !== null) {
                    $meta[] = sprintf('Total: %s %s', self::formatAmount((float) $total), self::currency($activity));
                }
            }
            if ($actionKey === 'transfer_completed') {
                $from = $properties['from_location'] ?? $properties['from_location_name'] ?? null;
                $to = $properties['to_location'] ?? $properties['to_location_name'] ?? null;
                if ($from || $to) {
                    $meta[] = sprintf('Transfert: %s → %s', $from ?? '—', $to ?? '—');
                }
                $count = $properties['items_count'] ?? $properties['lines_count'] ?? null;
                if ($count !== null) {
                    $meta[] = sprintf('Articles: %s', $count);
                }
            }
        }

        return array_values(array_filter($meta));
    }

    private static function reservationStatusLabel(?string $status): string
    {
        if (! $status) {
            return '—';
        }

        return [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'in_house' => 'En séjour',
            'checked_out' => 'Départ effectué',
            'cancelled' => 'Annulée',
            'no_show' => 'No-show',
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private static function hkStatusLabel(?string $status): string
    {
        if (! $status) {
            return '—';
        }

        return [
            'clean' => 'Propre',
            'dirty' => 'Sale',
            'inspected' => 'Inspectée',
            'out_of_order' => 'Hors service',
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private static function formatAmount(float $amount): string
    {
        return number_format($amount, 0, '.', ' ');
    }

    private static function currency(Activity $activity): string
    {
        $properties = $activity->properties?->toArray() ?? [];

        return (string) ($properties['currency'] ?? 'XAF');
    }

    private static function amountValue(Activity $activity): float
    {
        $properties = $activity->properties?->toArray() ?? [];
        $amount = $properties['amount'] ?? $properties['total_amount'] ?? null;

        if ($amount === null && isset($properties['payment_amount'])) {
            $amount = $properties['payment_amount'];
        }

        return $amount !== null ? (float) $amount : 0.0;
    }
}
