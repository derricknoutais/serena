<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\BarOrder;
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
use Illuminate\Support\Str;

class ActivityLabel
{
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
            'pos' => 'POS',
            'bar' => 'Bar',
            'cash' => 'Caisse',
            'folio' => 'Facturation',
            'payment' => 'Facturation',
        ];
    }

    public static function moduleLabel(?string $logName): string
    {
        if (! $logName) {
            return 'Activités';
        }

        return self::moduleMap()[$logName] ?? Str::title(str_replace('_', ' ', $logName));
    }

    /**
     * @return array<string, string>
     */
    public static function actionMap(): array
    {
        return [
            'confirmed' => 'Confirmée',
            'checked_in' => 'Check-in',
            'checked_out' => 'Check-out',
            'cancelled' => 'Annulée',
            'no_show' => 'No-show',
            'hk_dirty' => 'Marquée sale',
            'hk_clean' => 'Nettoyée',
            'hk_inspected' => 'Inspectée',
            'maintenance.ticket_created' => 'Ticket créé',
            'maintenance.intervention_submitted' => 'Soumise',
            'maintenance.intervention_approved' => 'Approuvée',
            'maintenance.intervention_rejected' => 'Rejetée',
            'maintenance.intervention_paid' => 'Payée',
            'stock.purchase_received' => 'Achat réceptionné',
            'stock.transfer_completed' => 'Transfert complété',
            'stock.inventory_posted' => 'Inventaire posté',
            'pos.stock_consumed' => 'Stock POS consommé',
            'pos.stock_returned' => 'Stock POS retourné',
            'bar.payment_captured' => 'Paiement encaissé',
            'payment.voided' => 'Paiement annulé',
            'payment.refunded' => 'Paiement remboursé',
        ];
    }

    public static function actionLabel(?string $action): string
    {
        if (! $action) {
            return 'Action';
        }

        return self::actionMap()[$action] ?? Str::title(str_replace('_', ' ', $action));
    }

    public static function subjectLabel(?Model $subject, Activity $activity): string
    {
        if ($subject instanceof Reservation) {
            return sprintf('Réservation %s', $subject->code ?? $subject->getKey());
        }

        if ($subject instanceof Room) {
            return sprintf('Chambre %s', $subject->number ?? $subject->getKey());
        }

        if ($subject instanceof MaintenanceTicket) {
            $title = $subject->title ? sprintf(' – %s', $subject->title) : '';

            return sprintf('Ticket #%s%s', $subject->getKey(), $title);
        }

        if ($subject instanceof MaintenanceIntervention) {
            return sprintf('Intervention #%s', $subject->getKey());
        }

        if ($subject instanceof StockPurchase) {
            return sprintf('Achat %s', $subject->reference_no ?? $subject->getKey());
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

        if ($subject instanceof Folio) {
            return sprintf('Folio %s', $subject->code ?? $subject->getKey());
        }

        if ($subject instanceof Payment) {
            return sprintf('Paiement %s', $subject->getKey());
        }

        $subjectType = $activity->subject_type ?? 'Élément';
        $subjectId = $activity->subject_id ?? '—';

        return sprintf('%s #%s', class_basename($subjectType), $subjectId);
    }

    public static function summary(Activity $activity, string $actionLabel, string $subjectLabel): string
    {
        $causerName = $activity->causer?->name;

        if ($causerName) {
            return sprintf('%s a %s — %s', $causerName, $actionLabel, $subjectLabel);
        }

        return sprintf('%s — %s', $actionLabel, $subjectLabel);
    }
}
