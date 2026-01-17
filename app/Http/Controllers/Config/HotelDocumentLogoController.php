<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HotelDocumentLogoController extends Controller
{
    use ResolvesActiveHotel;

    public function store(Request $request): JsonResponse
    {
        $this->authorize('hotels.documents.update');

        $hotel = $this->activeHotel($request);
        $disk = config('filesystems.document_logos_disk', 'public');

        if ($hotel === null) {
            abort(404, 'Aucun hôtel actif.');
        }

        $data = $request->validate([
            'logo' => ['required', 'image', 'max:12288'],
        ]);

        $documentSettings = $hotel->document_settings ?? [];
        $previousPath = $documentSettings['logo_path'] ?? null;
        unset($documentSettings['logo_url']);

        $path = $data['logo']->store(
            sprintf('tenants/%s/hotels/%s/documents', $hotel->tenant_id, $hotel->id),
            $disk,
        );

        if ($previousPath) {
            Storage::disk($disk)->delete($previousPath);
        }

        $documentSettings['logo_path'] = $path;
        $hotel->forceFill([
            'document_settings' => $documentSettings,
        ])->save();

        return response()->json([
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->authorize('hotels.documents.update');

        $hotel = $this->activeHotel($request);
        $disk = config('filesystems.document_logos_disk', 'public');

        if ($hotel === null) {
            abort(404, 'Aucun hôtel actif.');
        }

        $documentSettings = $hotel->document_settings ?? [];
        $path = $documentSettings['logo_path'] ?? null;
        unset($documentSettings['logo_url']);

        if ($path) {
            Storage::disk($disk)->delete($path);
        }

        $documentSettings['logo_path'] = null;
        $hotel->forceFill([
            'document_settings' => $documentSettings,
        ])->save();

        return response()->json([
            'path' => null,
            'url' => null,
        ]);
    }
}
