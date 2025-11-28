<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Tax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function index(Request $request): Response
    {
        $taxes = Tax::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Tax $tax) => [
                'id' => $tax->id,
                'name' => $tax->name,
                'rate' => $tax->rate,
                'type' => $tax->type,
                'is_active' => $tax->is_active,
            ]);

        return Inertia::render('Config/Taxes/TaxesIndex', [
            'taxes' => $taxes,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Config/Taxes/TaxesCreate');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'rate' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'string'],
            'is_city_tax' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $hotel = Hotel::query()->where('tenant_id', $request->user()->tenant_id)->firstOrFail();

        Tax::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'hotel_id' => $hotel->id,
        ]);

        return redirect()->route('ressources.taxes.index')->with('success', 'Taxe créée.');
    }

    public function edit(Request $request, int $id): Response
    {
        $tax = Tax::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        return Inertia::render('Config/Taxes/TaxesEdit', [
            'tax' => $tax,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'rate' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'string'],
            'is_city_tax' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $tax = Tax::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $tax->update($data);

        return redirect()->route('ressources.taxes.index')->with('success', 'Taxe mise à jour.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $tax = Tax::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $tax->delete();

        return redirect()->route('ressources.taxes.index')->with('success', 'Taxe supprimée.');
    }
}
