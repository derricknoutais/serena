<?php

namespace App\Http\Controllers\Frontdesk;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservationDetailsController extends Controller
{
    use ResolvesActiveHotel;

    public function show(Request $request, Reservation $reservation): Response|JsonResponse
    {
        $this->authorize('reservations.view_details');

        /** @var User $user */
        $user = $request->user();
        $hotelId = $this->activeHotelId($request);

        if ($hotelId === null
            || $reservation->tenant_id !== $user->tenant_id
            || $reservation->hotel_id !== $hotelId
        ) {
            abort(404);
        }

        $reservation->load([
            'guest',
            'room.roomType',
            'offer',
            'bookedBy',
            'mainFolio.itemsWithTrashed',
            'mainFolio.payments.paymentMethod',
            'mainFolio.payments.cashSession',
            'mainFolio.invoices.invoiceItems',
        ]);

        $folio = $reservation->mainFolio;

        $folioPayload = null;

        if ($folio) {
            $folioPayload = [
                'id' => $folio->id,
                'code' => $folio->code,
                'status' => $folio->status,
                'currency' => $folio->currency,
                'balance' => (float) $folio->balance,
                'total_charges' => (float) $folio->total_charges,
                'total_payments' => (float) $folio->total_payments,
                'items' => $folio->itemsWithTrashed->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'type' => $item->type,
                    'amount' => (float) $item->total_amount,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'currency' => $item->currency,
                    'deleted_at' => $item->deleted_at?->toDateTimeString(),
                ])->values(),
                'payments' => $folio->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'method' => $payment->paymentMethod?->name,
                    'entry_type' => $payment->entry_type,
                    'reference' => $payment->reference,
                    'created_by' => $payment->createdBy?->only(['id', 'name']),
                    'cash_session' => $payment->cashSession?->only(['id', 'code']),
                    'voided_at' => $payment->voided_at?->toDateTimeString(),
                    'parent_payment_id' => $payment->parent_payment_id,
                ])->values(),
                'invoices' => $folio->invoices->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'reference' => $invoice->reference,
                    'status' => $invoice->status,
                    'total_amount' => (float) $invoice->total_amount,
                    'currency' => $invoice->currency,
                    'created_at' => $invoice->created_at?->toDateTimeString(),
                    'items' => $invoice->invoiceItems->map(fn ($item) => [
                        'id' => $item->id,
                        'description' => $item->description,
                        'quantity' => (float) $item->quantity,
                        'total_amount' => (float) $item->total_amount,
                    ])->values(),
                ])->values(),
            ];
        }

        $payload = [
            'reservation' => [
                'id' => $reservation->id,
                'code' => $reservation->code,
                'status' => $reservation->status,
                'status_label' => $reservation->status_label,
                'guest' => $reservation->guest?->only(['id', 'full_name', 'email', 'phone']),
                'room' => $reservation->room?->only(['id', 'number']),
                'room_type' => $reservation->roomType?->only(['id', 'name']),
                'offer' => $reservation->offer?->only(['id', 'name']),
                'check_in_date' => $reservation->check_in_date?->toDateTimeString(),
                'check_out_date' => $reservation->check_out_date?->toDateTimeString(),
                'actual_check_in_at' => $reservation->actual_check_in_at?->toDateTimeString(),
                'actual_check_out_at' => $reservation->actual_check_out_at?->toDateTimeString(),
                'currency' => $reservation->currency,
                'total_amount' => (float) $reservation->total_amount,
                'notes' => $reservation->notes,
                'booked_by' => $reservation->bookedBy?->only(['id', 'name']),
            ],
            'folio' => $folioPayload,
            'capabilities' => [
                'confirm' => $user->can('reservations.confirm'),
                'check_in' => $user->can('reservations.check_in'),
                'check_out' => $user->can('reservations.check_out'),
                'cancel_reservation' => $user->can('reservations.cancel'),
                'delete_reservation' => $user->can('reservations.delete'),
                'move_room' => $user->can('reservations.move_room') || $user->can('reservations.change_room'),
                'change_guest' => $user->can('reservations.change_guest'),
                'change_offer' => $user->can('reservations.change_offer'),
                'override_times' => $user->can('reservations.override_stay_times'),
                'add_folio_item' => $user->can('folios.add_item'),
                'adjust_folio' => $user->can('folios.adjust'),
                'create_payment' => $user->can('payments.create'),
                'void_payment' => $user->can('payments.void'),
                'refund_payment' => $user->can('payments.refund'),
                'void_folio_item' => $user->can('folio_items.void') || $user->can('folios.items.void'),
                'generate_invoice' => $user->can('invoices.create') || $user->can('invoices.generate'),
                'force_status' => $user->can('reservations.force_status'),
                'edit_prices' => $user->can('reservations.edit_prices'),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Frontdesk/Reservations/Details', $payload);
    }
}
