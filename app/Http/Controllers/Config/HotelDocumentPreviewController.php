<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HotelDocumentPreviewController extends Controller
{
    use ResolvesActiveHotel;

    public function invoice(Request $request): View
    {
        $this->authorize('hotels.documents.update');

        $hotel = $this->activeHotel($request);

        if ($hotel === null) {
            abort(404, 'Aucun hotel actif.');
        }

        $invoice = new Invoice([
            'number' => 'APERCU-0001',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
            'currency' => $hotel->currency ?? 'XAF',
            'sub_total' => 45000,
            'tax_total' => 2500,
            'total_amount' => 47500,
            'billing_name' => 'Client Demo',
        ]);

        $items = new Collection([
            (object) [
                'description' => 'Nuitee (Chambre Deluxe)',
                'quantity' => 2,
                'unit_price' => 20000,
                'total_amount' => 40000,
            ],
            (object) [
                'description' => 'Petit-dejeuner',
                'quantity' => 2,
                'unit_price' => 2500,
                'total_amount' => 5000,
            ],
        ]);

        $guest = (object) [
            'full_name' => 'Client Demo',
            'email' => 'demo@example.test',
            'phone' => '+237600000000',
        ];

        return view('invoices.pdf', [
            'invoice' => $invoice,
            'hotel' => $hotel,
            'guest' => $guest,
            'items' => $items,
            'format' => $request->query('format', 'standard'),
            'isPreview' => true,
        ]);
    }
}
