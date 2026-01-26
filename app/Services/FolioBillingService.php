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

    /**
     * @param  array<string, mixed>  $options
     */
    public function regenerateInvoiceFromFolio(Folio $folio, Invoice $invoice, array $options = []): Invoice
    {
        return DB::transaction(function () use ($folio, $invoice, $options) {
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

            $invoice->items()->delete();

            $invoice->fill([
                'guest_id' => $guest?->id,
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
                'notes' => array_key_exists('notes', $options) ? $options['notes'] : $invoice->notes,
                'created_by_user_id' => $options['user_id'] ?? $invoice->created_by_user_id,
            ]);

            $invoice->save();

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

            return $invoice->refresh();
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
        $offerName = $offer?->name ?? $reservation->offer_name;
        $bundleNights = $this->resolveBundleNights($offer, $offerKind);
        $quantity = $offer?->billing_mode === 'fixed'
            ? 1
            : $this->calculateStayQuantity($offerKind, $checkIn, $checkOut, $bundleNights);

        $reservation->loadMissing('room');
        $roomNumber = $reservation->room?->number;
        $kindLabel = $this->resolveStayKindLabel($offerName, $offerKind);
        $description = $this->formatStayDescription($kindLabel, $checkIn, $checkOut, $roomNumber);
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
            'segment_start' => $checkIn->toDateString(),
            'segment_end' => $checkOut->toDateString(),
            'segment_version' => 1,
            'room_id' => $reservation->room_id,
            'room_number' => $roomNumber,
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
        $minutes = max(1, $checkIn->diffInMinutes($checkOut));
        $nights = max(1, (int) ceil($minutes / 1440));

        return match ($kind) {
            'short_stay' => 1,
            'weekend', 'package' => max(1, (int) ceil($nights / max(1, $bundleNights))),
            'full_day' => max(1, $nights),
            default => max(1, $nights),
        };
    }

    private function formatStayDescription(
        string $kindLabel,
        Carbon $segmentStart,
        Carbon $segmentEnd,
        ?string $roomNumber,
        ?string $prefix = null,
    ): string {
        $base = sprintf(
            '%s · Séjour du %s - %s',
            $kindLabel,
            $segmentStart->toDateString(),
            $segmentEnd->toDateString(),
        );

        $withRoom = sprintf('%s (Chambre %s)', $base, $roomNumber ?? '—');

        return $prefix ? sprintf('%s - %s', $prefix, $withRoom) : $withRoom;
    }

    private function resolveStayKindLabel(?string $offerName, ?string $kind): string
    {
        if (is_string($offerName) && $offerName !== '') {
            return $offerName;
        }

        return match ($kind) {
            'night' => 'Nuitée',
            'weekend' => 'Week-end',
            'package' => 'Forfait',
            'full_day' => 'Séjour 24h',
            'short_stay' => 'Séjour court',
            default => 'Séjour',
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

    public function addStayExtensionItem(
        Reservation $reservation,
        float $amount,
        Carbon $previousCheckOut,
        Carbon $newCheckOut,
        array $context = [],
    ): ?FolioItem {
        if (abs($amount) < 0.01) {
            return null;
        }

        $folio = $this->ensureMainFolioForReservation($reservation);

        $reservation->loadMissing('room');
        $roomNumber = $reservation->room?->number;
        $offerLabel = $context['offer_label']
            ?? $context['offer_name']
            ?? $reservation->offer?->name
            ?? $reservation->offer_name;

        $kindLabel = $this->resolveStayKindLabel($offerLabel, $reservation->offer?->kind ?? $reservation->offer_kind);
        $description = $context['description'] ?? 'Prolongation de séjour';
        $lineDescription = $context['line_description']
            ?? $this->formatStayDescription($kindLabel, $previousCheckOut, $newCheckOut, $roomNumber, $description);

        $quantity = max(1.0, (float) ($context['quantity'] ?? 1));
        $unitPrice = (float) ($context['unit_price'] ?? ($quantity > 0 ? $amount / $quantity : $amount));

        $meta = array_merge([
            'reservation_id' => $reservation->id,
            'reason' => $description,
            'offer_id' => $context['offer_id'] ?? $reservation->offer_id,
            'offer_kind' => $context['offer_kind'] ?? $reservation->offer?->kind ?? $reservation->offer_kind,
            'offer_name' => $offerLabel,
            'previous_check_out' => $previousCheckOut->toDateString(),
            'new_check_out' => $newCheckOut->toDateString(),
            'segment_start' => $previousCheckOut->toDateString(),
            'segment_end' => $newCheckOut->toDateString(),
            'segment_version' => 1,
            'room_id' => $reservation->room_id,
            'room_number' => $roomNumber,
        ], $context['meta'] ?? []);

        return $folio->addCharge([
            'description' => $lineDescription,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_amount' => $context['tax_amount'] ?? 0,
            'discount_percent' => $context['discount_percent'] ?? 0,
            'discount_amount' => $context['discount_amount'] ?? 0,
            'type' => 'stay_extension',
            'is_stay_item' => true,
            'meta' => $meta,
            'date' => $context['date'] ?? today()->toDateString(),
        ]);
    }

    public function resegmentStayForRoomChange(
        Reservation $reservation,
        ?Room $previousRoom,
        Room $newRoom,
        Carbon $pivotDate,
        float $oldUnitPrice,
        float $newUnitPrice,
        ?string $vacatedUsage = null,
    ): ?Carbon {
        if ($reservation->status !== Reservation::STATUS_IN_HOUSE) {
            return null;
        }

        $folio = $this->ensureMainFolioForReservation($reservation);

        if (! $folio->canEditItems()) {
            return null;
        }

        if (! $reservation->check_in_date || ! $reservation->check_out_date) {
            return null;
        }

        $reservation->loadMissing('room');

        $checkIn = $this->resolveStayStart($reservation);
        $checkOut = Carbon::parse($reservation->check_out_date);
        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            return null;
        }

        $offer = $reservation->offer;
        $kind = $offer?->kind ?? $reservation->offer_kind ?? 'night';
        $bundleNights = $this->resolveBundleNights($offer, $kind);
        $pivot = $pivotDate->copy();
        if ($vacatedUsage === 'not_used') {
            $pivot = $checkIn->copy();
        }

        $pivot = $pivot->max($checkIn)->min($checkOut);
        $pivotDateOnly = $pivot->copy()->startOfDay();
        $checkOutDateOnly = $checkOut->copy()->startOfDay();

        $activeStayItems = $folio->items()
            ->where('is_stay_item', true)
            ->get();

        if ($pivotDateOnly->greaterThanOrEqualTo($checkOutDateOnly)) {
            return $pivot;
        }

        foreach ($activeStayItems as $item) {
            $meta = is_array($item->meta) ? $item->meta : [];
            $itemKind = $meta['offer_kind'] ?? $offer?->kind ?? $reservation->offer_kind ?? 'night';
            $itemKindLabel = $this->resolveStayKindLabel($meta['offer_name'] ?? $offer?->name ?? $reservation->offer_name, $itemKind);
            $itemBundleNights = $this->resolveBundleNights($offer, $itemKind);
            [$segmentStart, $segmentEnd] = $this->resolveStayItemSegment($item, $checkIn, $checkOut);

            if (! $segmentStart || ! $segmentEnd) {
                continue;
            }

            if ($segmentEnd->lessThanOrEqualTo($pivotDateOnly)) {
                continue;
            }

            if ($segmentStart->greaterThanOrEqualTo($checkOutDateOnly)) {
                continue;
            }

            if ($segmentStart->lessThan($pivotDateOnly) && $segmentEnd->greaterThan($pivotDateOnly)) {
                $roomNumber = $meta['room_number'] ?? $previousRoom?->number ?? $reservation->room?->number;
                $meta = array_merge($meta, [
                    'segment_end' => $pivotDateOnly->toDateString(),
                    'segment_version' => $meta['segment_version'] ?? 1,
                    'room_id' => $meta['room_id'] ?? $previousRoom?->id ?? $reservation->room_id,
                    'room_number' => $roomNumber,
                ]);
                $item->meta = $meta;
                $item->description = $this->formatStayDescription(
                    $itemKindLabel,
                    $segmentStart,
                    $pivotDateOnly,
                    $roomNumber,
                    $this->resolveStayItemPrefix($item, $meta),
                );
                $item->quantity = $this->calculateSegmentQuantity($itemKind, $segmentStart, $pivotDateOnly, $itemBundleNights);
                $item->recalculateAmounts();
                $item->save();

                continue;
            }

            if ($segmentStart->greaterThanOrEqualTo($pivotDateOnly)) {
                $item->meta = array_merge($meta, [
                    'void_reason' => 'room_move_resegment',
                    'void_at' => $pivot->toDateTimeString(),
                ]);
                $item->save();
                $item->delete();
            }
        }

        if ($pivotDateOnly->lessThan($checkOutDateOnly)) {
            $this->createStaySegmentAfterPivot(
                $folio,
                $reservation,
                $pivotDateOnly->copy(),
                $checkOutDateOnly->copy(),
                $newUnitPrice,
                $newRoom,
                $pivot,
                $previousRoom,
            );
        }

        $folio->recalculateTotals();

        return $pivot;
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

        if ($bundle <= 0) {
            return $kind === 'weekend' ? 2 : 1;
        }

        return $bundle;
    }

    private function resolveStayStart(Reservation $reservation): Carbon
    {
        if ($reservation->actual_check_in_at) {
            return Carbon::parse($reservation->actual_check_in_at);
        }

        return Carbon::parse($reservation->check_in_date);
    }

    private function resolveRoomChangePivot(
        string $kind,
        Carbon $checkIn,
        Carbon $checkOut,
        Carbon $pivot,
        int $bundleNights,
        string $vacatedUsage,
    ): Carbon {
        if ($vacatedUsage === 'not_used') {
            return $checkIn->copy();
        }

        $bounded = $pivot->copy()->max($checkIn)->min($checkOut);

        if ($bounded->equalTo($checkIn) || $bounded->equalTo($checkOut)) {
            return $bounded;
        }

        if ($kind === 'short_stay') {
            return $bounded->greaterThan($checkIn) ? $checkOut->copy() : $checkIn->copy();
        }

        $unitMinutes = $this->resolveBillingUnitMinutes($kind, $bundleNights);
        if ($unitMinutes <= 0) {
            return $bounded;
        }

        $elapsedMinutes = $checkIn->diffInMinutes($bounded);
        $elapsedUnits = (int) floor($elapsedMinutes / $unitMinutes);

        return $checkIn->copy()->addMinutes($elapsedUnits * $unitMinutes)->max($checkIn)->min($checkOut);
    }

    private function resolveBillingUnitMinutes(string $kind, int $bundleNights): int
    {
        if (in_array($kind, ['weekend', 'package'], true)) {
            return 1440 * max(1, $bundleNights);
        }

        if (in_array($kind, ['night', 'full_day'], true)) {
            return 1440;
        }

        return 0;
    }

    private function calculateSegmentQuantity(
        string $kind,
        Carbon $segmentStart,
        Carbon $segmentEnd,
        int $bundleNights,
    ): float {
        if ($segmentEnd->lessThanOrEqualTo($segmentStart)) {
            return 0.0;
        }

        $minutes = $segmentStart->diffInMinutes($segmentEnd);
        $nights = (int) ceil($minutes / 1440);

        return match ($kind) {
            'short_stay' => 1.0,
            'weekend', 'package' => (float) max(1, (int) ceil($nights / max(1, $bundleNights))),
            'full_day' => (float) max(1, $nights),
            default => (float) max(1, $nights),
        };
    }

    public function calculateRoomMoveDeltaAfterPivot(
        Reservation $reservation,
        Carbon $pivot,
        float $oldUnitPrice,
        float $newUnitPrice,
    ): array {
        if (! $reservation->check_out_date) {
            return ['amount' => 0.0, 'quantity' => 0.0];
        }

        $checkOut = Carbon::parse($reservation->check_out_date);
        if ($checkOut->lessThanOrEqualTo($pivot)) {
            return ['amount' => 0.0, 'quantity' => 0.0];
        }

        $offer = $reservation->offer;
        $kind = $offer?->kind ?? $reservation->offer_kind ?? 'night';
        $bundleNights = $this->resolveBundleNights($offer, $kind);
        $quantity = $offer?->billing_mode === 'fixed'
            ? 1.0
            : (float) $this->calculateStayQuantity($kind, $pivot, $checkOut, $bundleNights);

        $amount = ($newUnitPrice - $oldUnitPrice) * $quantity;

        return [
            'amount' => $amount,
            'quantity' => $quantity,
        ];
    }

    private function createStaySegmentAfterPivot(
        Folio $folio,
        Reservation $reservation,
        Carbon $start,
        Carbon $end,
        float $unitPrice,
        ?Room $room,
        ?Carbon $movedAt = null,
        ?Room $previousRoom = null,
    ): void {
        $existing = $folio->items()
            ->where('is_stay_item', true)
            ->where('meta->segment_start', $start->toDateString())
            ->where('meta->segment_end', $end->toDateString())
            ->where('meta->room_id', $room?->id)
            ->exists();

        if ($existing) {
            return;
        }

        $this->createStaySegment(
            $folio,
            $reservation,
            $start,
            $end,
            $unitPrice,
            $room,
            'room_change',
            [
                'moved_at' => $movedAt?->toDateTimeString(),
                'old_room_id' => $previousRoom?->id,
                'new_room_id' => $room?->id,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function createStaySegment(
        Folio $folio,
        Reservation $reservation,
        Carbon $start,
        Carbon $end,
        float $unitPrice,
        ?Room $room,
        string $source,
        array $meta = [],
    ): void {
        $offer = $reservation->offer;
        $kind = $offer?->kind ?? $reservation->offer_kind ?? 'night';
        $bundleNights = $this->resolveBundleNights($offer, $kind);
        $quantity = $this->calculateSegmentQuantity($kind, $start, $end, $bundleNights);

        if ($quantity <= 0) {
            return;
        }

        $kindLabel = $this->resolveStayKindLabel($offer?->name ?? $reservation->offer_name, $kind);
        $description = $this->formatStayDescription(
            $kindLabel,
            $start,
            $end,
            $room?->number,
            $source === 'room_change' ? 'Transfert de séjour' : null,
        );

        $item = $folio->items()->make([
            'tenant_id' => $folio->tenant_id,
            'hotel_id' => $folio->hotel_id,
            'is_stay_item' => true,
            'type' => 'stay',
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_amount' => 0,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'date' => $start->toDateString(),
            'meta' => [
                'segment_start' => $start->toDateString(),
                'segment_end' => $end->toDateString(),
                'segment_version' => 1,
                'room_id' => $room?->id,
                'room_number' => $room?->number,
                'offer_id' => $reservation->offer_id,
                'offer_kind' => $kind,
                'offer_name' => $offer?->name ?? $reservation->offer_name,
                'unit_price_snapshot' => $unitPrice,
                'source' => $source,
                'is_transfer' => $source === 'room_change' ? true : null,
                ...array_filter($meta, fn ($value) => $value !== null),
            ],
        ]);

        $item->recalculateAmounts();
        $item->save();
    }

    /**
     * @return array{0:?Carbon,1:?Carbon}
     */
    private function resolveStayItemSegment(FolioItem $item, Carbon $checkIn, Carbon $checkOut): array
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        $segmentStart = $meta['segment_start'] ?? null;
        $segmentEnd = $meta['segment_end'] ?? null;

        if (is_string($segmentStart) && is_string($segmentEnd)) {
            return [Carbon::parse($segmentStart)->startOfDay(), Carbon::parse($segmentEnd)->startOfDay()];
        }

        $parsed = $this->parseSegmentFromDescription($item->description);
        if ($parsed) {
            return $parsed;
        }

        return [$checkIn->copy()->startOfDay(), $checkOut->copy()->startOfDay()];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveStayItemPrefix(FolioItem $item, array $meta): ?string
    {
        if (($meta['is_transfer'] ?? false) === true) {
            return 'Transfert de séjour';
        }

        if ($item->type === 'stay_extension') {
            return 'Prolongation de séjour';
        }

        return null;
    }

    /**
     * @return array{0:Carbon,1:Carbon}|null
     */
    private function parseSegmentFromDescription(?string $description): ?array
    {
        if (! is_string($description)) {
            return null;
        }

        if (preg_match('/Séjour du (\d{4}-\d{2}-\d{2}) au (\d{4}-\d{2}-\d{2})/', $description, $matches)) {
            return [
                Carbon::parse($matches[1])->startOfDay(),
                Carbon::parse($matches[2])->startOfDay(),
            ];
        }

        if (preg_match('/Séjour du (\d{2}\/\d{2}\/\d{4})\s*(?:au|[–-])\s*(\d{2}\/\d{2}\/\d{4})/', $description, $matches)) {
            return [
                Carbon::createFromFormat('d/m/Y', $matches[1])->startOfDay(),
                Carbon::createFromFormat('d/m/Y', $matches[2])->startOfDay(),
            ];
        }

        if (preg_match('/\\((\\d{2}\\/\\d{2})\\s*[–-]\\s*(\\d{2}\\/\\d{2})\\)/', $description, $matches)) {
            $start = Carbon::parse($matches[1])->startOfDay();
            $end = Carbon::parse($matches[2])->startOfDay();
            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->copy()->addYear();
            }

            return [$start, $end];
        }

        return null;
    }

    private function replaceStayDescriptionDates(?string $description, Carbon $start, Carbon $end): ?string
    {
        if (! is_string($description)) {
            return $description;
        }

        $updated = $description;

        if (preg_match('/Séjour du (\d{4}-\d{2}-\d{2}) au (\d{4}-\d{2}-\d{2})/', $updated)) {
            $replaced = preg_replace(
                '/Séjour du (\d{4}-\d{2}-\d{2}) au (\d{4}-\d{2}-\d{2})/',
                sprintf('Séjour du %s au %s', $start->toDateString(), $end->toDateString()),
                $updated,
            );

            return $replaced ?? $updated;
        }

        if (preg_match('/\\((\\d{2}\\/\\d{2})\\s*[–-]\\s*(\\d{2}\\/\\d{2})\\)/', $updated)) {
            $replaced = preg_replace(
                '/\\((\\d{2}\\/\\d{2})\\s*[–-]\\s*(\\d{2}\\/\\d{2})\\)/',
                sprintf('(%s – %s)', $start->format('d/m'), $end->format('d/m')),
                $updated,
            );

            return $replaced ?? $updated;
        }

        return $updated;
    }
}
