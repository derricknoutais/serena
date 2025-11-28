<?php

namespace App\Http\Controllers\Config;

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
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with(['category'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'unit_price' => $product->unit_price,
                'account_code' => $product->account_code,
                'is_active' => $product->is_active,
                'category' => $product->category?->name,
            ]);

        return Inertia::render('Config/Products/ProductsIndex', [
            'products' => $products,
        ]);
    }

    public function create(Request $request): Response
    {
        $categories = ProductCategory::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $taxes = Tax::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Config/Products/ProductsCreate', [
            'categories' => $categories,
            'taxes' => $taxes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'name' => ['required', 'string'],
            'sku' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'account_code' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $hotel = Hotel::query()->where('tenant_id', $request->user()->tenant_id)->firstOrFail();

        Product::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'hotel_id' => $hotel->id,
        ]);

        return redirect()->route('ressources.products.index')->with('success', 'Produit créé.');
    }

    public function edit(Request $request, int $id): Response
    {
        $product = Product::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $categories = ProductCategory::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $taxes = Tax::query()
            ->where('tenant_id', $request->user()->tenant_id)
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
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $product->update($data);

        return redirect()->route('ressources.products.index')->with('success', 'Produit mis à jour.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $product = Product::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $product->delete();

        return redirect()->route('ressources.products.index')->with('success', 'Produit supprimé.');
    }
}
