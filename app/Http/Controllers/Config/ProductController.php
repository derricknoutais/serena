<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('products.view');

        $products = Product::query()
            ->with(['category'])
            ->when($this->activeHotelId($request), fn ($q) => $q->where('hotel_id', $this->activeHotelId($request)))
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'product_category_id' => $product->product_category_id,
                'unit_price' => $product->unit_price,
                'account_code' => $product->account_code,
                'is_active' => $product->is_active,
                'category' => $product->category?->name,
                'tax_id' => $product->tax_id,
                'sku' => $product->sku,
            ]);

        $categories = ProductCategory::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($this->activeHotelId($request), fn ($q) => $q->where('hotel_id', $this->activeHotelId($request)))
            ->orderBy('name')
            ->get(['id', 'name']);

        $taxes = Tax::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($this->activeHotelId($request), fn ($q) => $q->where('hotel_id', $this->activeHotelId($request)))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Config/Products/ProductsIndex', [
            'products' => $products,
            'categories' => $categories,
            'taxes' => $taxes,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('products.create');

        $categories = ProductCategory::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($this->activeHotelId($request), fn ($q) => $q->where('hotel_id', $this->activeHotelId($request)))
            ->orderBy('name')
            ->get(['id', 'name']);

        $taxes = Tax::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($this->activeHotelId($request), fn ($q) => $q->where('hotel_id', $this->activeHotelId($request)))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Config/Products/ProductsCreate', [
            'categories' => $categories,
            'taxes' => $taxes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('products.create');

        $data = $request->validate([
            'product_category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'name' => ['required', 'string'],
            'sku' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'account_code' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $hotel = Hotel::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($this->activeHotelId($request), fn ($q) => $q->where('id', $this->activeHotelId($request)))
            ->firstOrFail();

        Product::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'hotel_id' => $hotel->id,
        ]);

        return redirect()->route('ressources.products.index')->with('success', 'Produit créé.');
    }

    public function edit(Request $request, int $id): Response
    {
        $this->authorize('products.update');

        $product = Product::query()
            ->where('hotel_id', $this->activeHotelId($request))
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $categories = ProductCategory::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('hotel_id', $product->hotel_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $taxes = Tax::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('hotel_id', $product->hotel_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Config/Products/ProductsEdit', [
            'product' => $product,
            'categories' => $categories,
            'taxes' => $taxes,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('products.update');

        $data = $request->validate([
            'product_category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'name' => ['required', 'string'],
            'sku' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'account_code' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product = Product::query()
            ->where('hotel_id', $this->activeHotelId($request))
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $product->update($data);

        return redirect()->route('ressources.products.index')->with('success', 'Produit mis à jour.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->authorize('products.delete');

        $product = Product::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $product->delete();

        return redirect()->route('ressources.products.index')->with('success', 'Produit supprimé.');
    }
}
