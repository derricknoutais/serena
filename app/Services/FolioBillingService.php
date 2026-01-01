<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FolioBillingService
{
    public function ensureMainFolioForReservation(Reservation $reservation): Folio
    {
        if ($reservation->relationLoaded('mainFolio') && $reservation->mainFolio) {
            return $reservation->mainFolio;
        }

        $existing = $reservation->mainFolio()->first();

        if ($existing) {
            return $existing;
        }

        return $reservation->folios()->create([
            'tenant_id' => $reservation->tenant_id,
            'hotel_id' => $reservation->hotel_id,
            'guest_id' => $reservation->guest_id,
            'code' => sprintf('FOL-%s', $reservation->code),
            'status' => 'open',
            'is_main' => true,
            'type' => 'reservation',
            'origin' => 'reservation',
            'currency' => $reservation->currency,
            'billing_name' => $reservation->guest?->full_name,
            'opened_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function generateInvoiceFromFolio(Folio $folio, array $options = []): Invoice
    {
        return DB::transaction(function () use ($folio, $options) {
            $folio->loadMissing([
                'items' => fn ($query) => $query->whereNull('deleted_at'),
                'reservation.guest',
            ]);
            $guest = $folio->reservation?->guest;
            $items = $folio->items;

            foreach ($items as $item) {
                $item->recalculateAmounts();
                $item->save();
            }

            $subTotal = (float) $items->sum('net_amount');
            $taxTotal = (float) $items->sum('tax_amount');
            $totalAmount = $subTotal + $taxTotal;

            $invoice = Invoice::query()->create([
                'tenant_id' => $folio->tenant_id,
                'hotel_id' => $folio->hotel_id,
                'folio_id' => $folio->id,
                'guest_id' => $guest?->id,
                'number' => $this->generateInvoiceNumber($folio->hotel_id),
                'status' => Invoice::STATUS_ISSUED,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'currency' => $folio->currency,
                'sub_total' => $subTotal,
                'tax_total' => $taxTotal,
                'total_amount' => $totalAmount,
                'billing_name' => $guest?->full_name,
                'billing_address' => $guest?->address,
                'billing_tax_id' => $guest?->document_number,
                'notes' => $options['notes'] ?? null,
                'created_by_user_id' => $options['user_id'] ?? null,
            ]);

            $sortOrder = 1;

            foreach ($items as $folioItem) {
                InvoiceItem::query()->create([
                    'tenant_id' => $folio->tenant_id,
                    'invoice_id' => $invoice->id,
                    'folio_item_id' => $folioItem->id,
                    'description' => $folioItem->description,
                    'quantity' => $folioItem->quantity,
                    'unit_price' => $folioItem->unit_price,
                    'tax_amount' => $folioItem->tax_amount,
                    'total_amount' => $folioItem->total_amount,
                    'sort_order' => $sortOrder++,
                ]);
            }

            if (! empty($options['close_folio'])) {
                $folio->close();
            }

            return $invoice;
        });
    }

    protected function generateInvoiceNumber(int $hotelId): string
    {
        return sprintf('INV-%s-%s', $hotelId, now()->format('YmdHis'));
    }

    public function syncStayChargeFromReservation(Reservation $reservation): Folio
    {
        $folio = $this->ensureMainFolioForReservation($reservation);

        if (! $reservation->check_in_date || ! $reservation->check_out_date) {
            return $folio;
        }

        $checkIn = Carbon::parse($reservation->check_in_date);
        $checkOut = Carbon::parse($reservation->check_out_date);

        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            return $folio;
        }

        $offer = $reservation->offer;
        $offerKind = $offer?->kind ?? $reservation->offer_kind ?? 'night';
        $offerName = $offer?->name ?? $reservation->offer_name ?? 'Séjour';
        $bundleNights = $this->resolveBundleNights($offer, $offerKind);
        $quantity = $this->calculateStayQuantity($offerKind, $checkIn, $checkOut, $bundleNights);

        $description = $this->buildStayDescription($offerKind, $offerName, $checkIn, $checkOut);
        $unitPrice = (float) $reservation->unit_price;
        $offerPrice = null;

        if ($reservation->offer_id && $reservation->room_type_id) {
            $offerPrice = OfferRoomTypePrice::query()
                ->where('tenant_id', $reservation->tenant_id)
                ->where('hotel_id', $reservation->hotel_id)
                ->where('room_type_id', $reservation->room_type_id)
                ->where('offer_id', $reservation->offer_id)
                ->value('price');
        }

        if ($offerPrice !== null) {
            $unitPrice = (float) $offerPrice;
        }
        $stayMeta = [
            'reservation_id' => $reservation->id,
            'offer_id' => $reservation->offer_id,
            'offer_name' => $offerName,
            'offer_kind' => $offerKind,
        ];

        $stayItem = $folio->items()->where('is_stay_item', true)->first();

        if (! $stayItem) {
            $stayItem = $folio->items()->make([
                'tenant_id' => $folio->tenant_id,
                'hotel_id' => $folio->hotel_id,
                'is_stay_item' => true,
                'description' => $description,
                'type' => 'stay',
                'meta' => $stayMeta,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_amount' => 0,
                'discount_percent' => 0,
                'discount_amount' => 0,
            ]);
        } else {
            $stayItem->description = $description;
        }

        $stayItem->type = 'stay';
        $stayItem->meta = $stayMeta;
        $stayItem->quantity = $quantity;
        $stayItem->unit_price = $unitPrice;
        $stayItem->discount_percent = 0;
        $stayItem->discount_amount = 0;
        $stayItem->tax_amount = 0;
        $stayItem->date = today();
        $stayItem->recalculateAmounts();
        $stayItem->save();

        if ($offerPrice !== null && (float) $reservation->unit_price !== (float) $offerPrice) {
            $reservation->forceFill([
                'unit_price' => (float) $offerPrice,
                'base_amount' => $quantity * (float) $offerPrice,
                'total_amount' => ($quantity * (float) $offerPrice) + (float) $reservation->tax_amount,
            ])->save();
        }

        $folio->recalculateTotals();

        return $folio;
    }

    public function calculateStayQuantity(string $kind, Carbon $checkIn, Carbon $checkOut, int $bundleNights = 1): int
    {
        $nights = max(1, $checkIn->diffInDays($checkOut));

        return match ($kind) {
            'short_stay' => 1,
            'weekend', 'package' => max(1, (int) ceil($nights / max(1, $bundleNights))),
            'full_day' => max(1, $nights),
            default => max(1, $nights),
        };
    }

    private function buildStayDescription(string $kind, string $offerName, Carbon $checkIn, Carbon $checkOut): string
    {
        $start = $checkIn->toDateString();
        $end = $checkOut->toDateString();

        return match ($kind) {
            'short_stay' => sprintf('%s · Séjour court (~3h) le %s', $offerName, $start),
            'weekend' => sprintf('%s · Séjour week-end du %s au %s', $offerName, $start, $end),
            'full_day' => sprintf('%s · Séjour 24h du %s au %s', $offerName, $start, $end),
            default => sprintf('%s · Séjour du %s au %s', $offerName, $start, $end),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function addStayAdjustment(Reservation $reservation, float $amount, string $description, array $context = []): void
    {
        if (abs($amount) < 0.01) {
            return;
        }

        $folio = $this->ensureMainFolioForReservation($reservation);

        $offerLabel = $context['offer_name']
            ?? $reservation->offer?->name
            ?? $reservation->offer_name
            ?? 'Séjour';

        $lineDescription = $context['line_description'] ?? sprintf('%s - %s', $description, $offerLabel);

        $quantity = (float) ($context['quantity'] ?? 1);
        $unitPrice = (float) ($context['unit_price'] ?? $amount);

        $meta = array_merge([
            'reservation_id' => $reservation->id,
            'reason' => $description,
        ], $context['meta'] ?? []);

        $folio->addCharge([
            'description' => $lineDescription,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_amount' => $context['tax_amount'] ?? 0,
            'discount_percent' => $context['discount_percent'] ?? 0,
            'discount_amount' => $context['discount_amount'] ?? 0,
            'type' => 'stay_adjustment',
            'meta' => $meta,
        ]);
    }

    public function resegmentStayForRoomChange(
        Reservation $reservation,
        ?Room $previousRoom,
        Room $newRoom,
        Carbon $pivotDate,
        float $oldUnitPrice,
        float $newUnitPrice,
    ): void {
        if ($reservation->status !== Reservation::STATUS_IN_HOUSE) {
            return;
        }

        $folio = $this->ensureMainFolioForReservation($reservation);

        if (! $folio->canEditItems()) {
            return;
        }

        $folio->items()
            ->where('is_stay_item', true)
            ->get()
            ->each(static fn (FolioItem $item) => $item->delete());

        $checkIn = Carbon::parse($reservation->check_in_date);
        $checkOut = Carbon::parse($reservation->check_out_date);
        $pivot = $pivotDate->copy()->startOfDay();
        $pivot = $pivot->max($checkIn)->min($checkOut);

        $offer = $reservation->offer;
        $kind = $offer?->kind ?? $reservation->offer_kind ?? 'night';
        $bundleNights = $this->resolveBundleNights($offer, $kind);

        $segments = [];

        if ($pivot->greaterThan($checkIn)) {
            $segments[] = [
                'start' => $checkIn->copy(),
                'end' => $pivot->copy(),
                'unit_price' => $oldUnitPrice,
                'room' => $previousRoom,
            ];
        }

        if ($pivot->lessThan($checkOut)) {
            $segments[] = [
                'start' => $pivot->copy(),
                'end' => $checkOut->copy(),
                'unit_price' => $newUnitPrice,
                'room' => $newRoom,
            ];
        }

        foreach ($segments as $segment) {
            $quantity = $this->calculateStayQuantity($kind, $segment['start'], $segment['end'], $bundleNights);

            if ($quantity <= 0) {
                continue;
            }

            $description = sprintf(
                'Séjour %s (%s – %s)',
                $this->roomLabel($segment['room']),
                $segment['start']->format('d/m'),
                $segment['end']->format('d/m'),
            );

            $item = $folio->items()->make([
                'tenant_id' => $folio->tenant_id,
                'hotel_id' => $folio->hotel_id,
                'is_stay_item' => true,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $segment['unit_price'],
                'tax_amount' => 0,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'date' => $segment['start']->toDateString(),
                'meta' => [
                    'segment_start' => $segment['start']->toDateString(),
                    'segment_end' => $segment['end']->toDateString(),
                    'room_id' => $segment['room']?->id,
                ],
            ]);

            $item->recalculateAmounts();
            $item->save();
        }

        $folio->recalculateTotals();
    }

    private function roomLabel(?Room $room): string
    {
        if (! $room) {
            return 'chambre';
        }

        $typeName = $room->roomType?->name;
        $number = $room->number;

        if ($typeName && $number) {
            return sprintf('chambre %s (%s)', $typeName, $number);
        }

        if ($typeName) {
            return sprintf('chambre %s', $typeName);
        }

        if ($number) {
            return sprintf('chambre %s', $number);
        }

        return 'chambre';
    }

    private function resolveBundleNights(?Offer $offer, string $kind): int
    {
        if (! in_array($kind, ['weekend', 'package'], true)) {
            return 1;
        }

        if (! $offer) {
            return $kind === 'weekend' ? 2 : 1;
        }

        $bundle = 0;

        if ($offer->time_rule === 'weekend_window') {
            $bundle = (int) ($offer->time_config['checkout']['max_days_after_checkin'] ?? 0);
        } elseif ($offer->time_rule === 'fixed_checkout') {
            $bundle = (int) ($offer->time_config['day_offset'] ?? 0);
        } elseif ($offer->time_rule === 'rolling') {
            $minutes = (int) ($offer->time_config['duration_minutes'] ?? 0);
            $bundle = $minutes > 0 ? (int) ceil($minutes / 1440) : 0;
        } elseif ($offer->time_rule === 'fixed_window') {
            $startTime = $offer->time_config['start_time'] ?? null;
            $endTime = $offer->time_config['end_time'] ?? null;
            if (is_string($startTime) && is_string($endTime)) {
                [$startHour, $startMinute] = array_map('intval', explode(':', $startTime.':0'));
                [$endHour, $endMinute] = array_map('intval', explode(':', $endTime.':0'));
                $startMinutes = ($startHour * 60) + $startMinute;
                $endMinutes = ($endHour * 60) + $endMinute;
                if ($endMinutes <= $startMinutes) {
                    $endMinutes += 1440;
                }
                $duration = $endMinutes - $startMinutes;
                $bundle = $duration > 0 ? (int) ceil($duration / 1440) : 0;
            }
        }

        if ($bundle <= 0 && $offer->fixed_duration_hours !== null) {
            $bundle = (int) ceil(((int) $offer->fixed_duration_hours) / 24);
        }

        if ($bundle <= 0) {
            return $kind === 'weekend' ? 2 : 1;
        }

        return $bundle;
    }
}
