<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockItem;
use App\Models\StorageLocation;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('products.view');

        $hotelId = $this->activeHotelId($request);

        $products = Product::query()
            ->with(['category', 'stockItem'])
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'product_category_id' => $product->product_category_id,
                'unit_price' => $product->unit_price,
                'is_active' => $product->is_active,
                'category' => $product->category?->name,
                'tax_id' => $product->tax_id,
                'sku' => $product->sku,
                'manage_stock' => (bool) $product->manage_stock,
                'stock_item_id' => $product->stock_item_id,
                'stock_location_id' => $product->stock_location_id,
                'stock_quantity_per_unit' => (float) ($product->stock_quantity_per_unit ?? 1),
                'default_purchase_price' => (float) ($product->stockItem?->default_purchase_price ?? 0),
            ]);

        $categories = ProductCategory::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $taxes = Tax::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $stockItems = StockItem::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->where('is_active', true)
            ->where('item_category', 'bar')
            ->orderBy('name')
            ->get(['id', 'name', 'unit']);

        $storageLocations = StorageLocation::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->where('is_active', true)
            ->where('category', 'bar')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Config/Products/ProductsIndex', [
            'products' => $products,
            'categories' => $categories,
            'taxes' => $taxes,
            'stockItems' => $stockItems,
            'storageLocations' => $storageLocations,
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

        $hotel = Hotel::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($this->activeHotelId($request), fn ($q) => $q->where('id', $this->activeHotelId($request)))
            ->firstOrFail();

        $data = $request->validate([
            'product_category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'name' => ['required', 'string'],
            'sku' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'is_active' => ['sometimes', 'boolean'],
            'manage_stock' => ['sometimes', 'boolean'],
            'stock_item_id' => [
                'nullable',
                Rule::exists('stock_items', 'id')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('hotel_id', $hotel->id),
            ],
            'stock_location_id' => [
                'nullable',
                Rule::exists('storage_locations', 'id')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('hotel_id', $hotel->id),
            ],
            'stock_quantity_per_unit' => ['nullable', 'numeric', 'min:0.01'],
            'default_purchase_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $canManageBarStock = $request->user()->can('stock.manage_bar_settings');

        if (! $canManageBarStock) {
            $data['manage_stock'] = false;
        }

        if (! ($data['manage_stock'] ?? false)) {
            $data['stock_item_id'] = null;
            $data['stock_location_id'] = null;
            $data['stock_quantity_per_unit'] = 1;
        } elseif (! isset($data['stock_quantity_per_unit']) || (float) $data['stock_quantity_per_unit'] <= 0) {
            return back()->withErrors(['stock_quantity_per_unit' => 'La quantité consommée doit être supérieure à zéro.']);
        }

        $data['stock_item_id'] = $this->normalizeStockItemId($data['stock_item_id'] ?? null);

        if ($data['manage_stock'] ?? false) {
            $data['stock_item_id'] = $data['stock_item_id']
                ?? $this->createStockItemForProduct($hotel, $data, $request->user())->id;

            if (isset($data['default_purchase_price'])) {
                StockItem::query()
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('hotel_id', $hotel->id)
                    ->where('id', $data['stock_item_id'])
                    ->update([
                        'default_purchase_price' => $data['default_purchase_price'],
                        'currency' => $hotel->currency ?? 'XAF',
                    ]);
            }
        }

        $product = new Product([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'hotel_id' => $hotel->id,
        ]);
        $product->account_code = '';
        $product->save();

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

        $product = Product::query()
            ->where('hotel_id', $this->activeHotelId($request))
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $hotel = Hotel::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($product->hotel_id);

        $data = $request->validate([
            'product_category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'name' => ['required', 'string'],
            'sku' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'is_active' => ['sometimes', 'boolean'],
            'manage_stock' => ['sometimes', 'boolean'],
            'stock_item_id' => [
                'nullable',
                Rule::exists('stock_items', 'id')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('hotel_id', $product->hotel_id),
            ],
            'stock_location_id' => [
                'nullable',
                Rule::exists('storage_locations', 'id')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('hotel_id', $product->hotel_id),
            ],
            'stock_quantity_per_unit' => ['nullable', 'numeric', 'min:0.01'],
            'default_purchase_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $canManageBarStock = $request->user()->can('stock.manage_bar_settings');

        if (! $canManageBarStock) {
            $data['manage_stock'] = $product->manage_stock;
            $data['stock_item_id'] = $product->stock_item_id;
            $data['stock_location_id'] = $product->stock_location_id;
            $data['stock_quantity_per_unit'] = $product->stock_quantity_per_unit;
        }

        if (! ($data['manage_stock'] ?? false)) {
            $data['stock_item_id'] = null;
            $data['stock_location_id'] = null;
            $data['stock_quantity_per_unit'] = 1;
        } elseif (! isset($data['stock_quantity_per_unit']) || (float) $data['stock_quantity_per_unit'] <= 0) {
            return back()->withErrors(['stock_quantity_per_unit' => 'La quantité consommée doit être supérieure à zéro.']);
        }

        $data['stock_item_id'] = $this->normalizeStockItemId($data['stock_item_id'] ?? null);

        if ($data['manage_stock'] ?? false) {
            if (! $data['stock_item_id'] && ! $product->stock_item_id) {
                $data['stock_item_id'] = $this->createStockItemForProduct(
                    $hotel,
                    $data,
                    $request->user(),
                )->id;
            }

            if (! $data['stock_item_id'] && $product->stock_item_id) {
                $data['stock_item_id'] = $product->stock_item_id;
            }

            if (isset($data['default_purchase_price']) && $data['stock_item_id']) {
                StockItem::query()
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('hotel_id', $product->hotel_id)
                    ->where('id', $data['stock_item_id'])
                    ->update([
                        'default_purchase_price' => $data['default_purchase_price'],
                        'currency' => $hotel->currency ?? 'XAF',
                    ]);
            }
        }

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

    private function normalizeStockItemId(mixed $value): ?int
    {
        $id = (int) ($value ?? 0);

        return $id > 0 ? $id : null;
    }

    private function createStockItemForProduct(Hotel $hotel, array $data, ?User $user = null): StockItem
    {
        return StockItem::query()->create([
            'tenant_id' => $user?->tenant_id ?? $hotel->tenant_id,
            'hotel_id' => $hotel->id,
            'name' => $data['name'] ?? 'Produit Bar',
            'sku' => $data['sku'] ?? null,
            'unit' => 'PC',
            'item_category' => 'bar',
            'is_active' => true,
            'default_purchase_price' => (float) ($data['default_purchase_price'] ?? 0),
            'currency' => $hotel->currency ?? 'XAF',
            'reorder_point' => 0,
            'is_kit' => false,
        ]);
    }
}
