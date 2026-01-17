<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateHotelRequest;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotelConfigController extends Controller
{
    use ResolvesActiveHotel;

    public function edit(Request $request): Response
    {
        $hotel = $this->activeHotel($request);

        if ($hotel === null) {
            $hotel = Hotel::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->first();

            if ($hotel !== null) {
                $request->user()->forceFill(['active_hotel_id' => $hotel->id])->save();
                $request->user()->hotels()->syncWithoutDetaching([$hotel->id]);
                $request->session()->put('active_hotel_id', $hotel->id);
            }
        }

        return Inertia::render('Config/Hotel/HotelIndex', [
            'hotel' => $hotel,
            'flash' => [
                'success' => session('success'),
            ],
        ]);
    }

    public function update(UpdateHotelRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $staySettings = [
            'standard_checkin_time' => $data['check_in_time'] ?? null,
            'standard_checkout_time' => $data['check_out_time'] ?? null,
            'early_checkin' => [
                'policy' => $data['early_policy'] ?? 'free',
                'fee_type' => $data['early_fee_type'] ?? 'flat',
                'fee_value' => $data['early_fee_value'] ?? 0,
                'cutoff_time' => $data['early_cutoff_time'] ?? null,
            ],
            'late_checkout' => [
                'policy' => $data['late_policy'] ?? 'free',
                'fee_type' => $data['late_fee_type'] ?? 'flat',
                'fee_value' => $data['late_fee_value'] ?? 0,
                'max_time' => $data['late_max_time'] ?? null,
            ],
        ];

        $data['stay_settings'] = $staySettings;
        unset(
            $data['early_policy'],
            $data['early_fee_type'],
            $data['early_fee_value'],
            $data['early_cutoff_time'],
            $data['late_policy'],
            $data['late_fee_type'],
            $data['late_fee_value'],
            $data['late_max_time'],
        );

        $hotel = $this->activeHotel($request);
        $existingDocumentSettings = $hotel?->document_settings ?? [];
        $canUpdateDocuments = $request->user()->can('hotels.documents.update');

        unset($existingDocumentSettings['logo_url']);

        $documentSettings = $existingDocumentSettings;

        if ($canUpdateDocuments) {
            $documentSettings = array_replace_recursive($existingDocumentSettings, [
                'display_name' => $data['document_display_name'] ?? $data['name'],
                'contact' => [
                    'address' => $data['document_contact_address'] ?? null,
                    'phone' => $data['document_contact_phone'] ?? null,
                    'email' => $data['document_contact_email'] ?? null,
                ],
                'legal' => [
                    'nif' => $data['document_legal_nif'] ?? null,
                    'rccm' => $data['document_legal_rccm'] ?? null,
                ],
                'header_text' => $data['document_header_text'] ?? null,
                'footer_text' => $data['document_footer_text'] ?? null,
            ]);
        }

        unset($documentSettings['logo_url']);

        $data['document_settings'] = $documentSettings;
        unset(
            $data['document_display_name'],
            $data['document_contact_address'],
            $data['document_contact_phone'],
            $data['document_contact_email'],
            $data['document_legal_nif'],
            $data['document_legal_rccm'],
            $data['document_header_text'],
            $data['document_footer_text'],
        );

        if ($hotel === null) {
            $hotel = Hotel::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->firstOrCreate([
                    'tenant_id' => $request->user()->tenant_id,
                    'name' => $data['name'],
                ], $data);
        } else {
            $hotel->update($data);
        }

        $request->user()->forceFill(['active_hotel_id' => $hotel->id])->save();
        $request->user()->hotels()->syncWithoutDetaching([$hotel->id]);
        $request->session()->put('active_hotel_id', $hotel->id);

        return redirect()
            ->route('ressources.hotel.edit')
            ->with('success', 'Informations de l’hôtel mises à jour.');
    }
}
