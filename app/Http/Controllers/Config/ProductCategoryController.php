<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $categories = ProductCategory::query()
            ->when($this->activeHotelId($request), fn ($q) => $q->where('hotel_id', $this->activeHotelId($request)))
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (ProductCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'is_active' => $category->is_active,
            ]);

        return Inertia::render('Config/ProductCategories/ProductCategoriesIndex', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $hotel = Hotel::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($this->activeHotelId($request), fn ($q) => $q->where('id', $this->activeHotelId($request)))
            ->firstOrFail();

        ProductCategory::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'hotel_id' => $hotel->id,
        ]);

        return redirect()->route('ressources.product-categories.index')->with('success', 'Catégorie créée.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = ProductCategory::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($this->activeHotelId($request), fn ($q) => $q->where('hotel_id', $this->activeHotelId($request)))
            ->findOrFail($id);

        $category->update($data);

        return redirect()->route('ressources.product-categories.index')->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $category = ProductCategory::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($this->activeHotelId($request), fn ($q) => $q->where('hotel_id', $this->activeHotelId($request)))
            ->findOrFail($id);

        $category->delete();

        return redirect()->route('ressources.product-categories.index')->with('success', 'Catégorie supprimée.');
    }
}
