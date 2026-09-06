<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Enums\StorageType;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    /**
     * The searchable, filterable product list.
     */
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with(['category', 'brand', 'unit', 'packUnit'])
            ->search($request->string('search')->toString() ?: null)
            ->when($request->filled('category'), fn ($query) => $query->where('category_id', $request->integer('category')))
            ->when($request->filled('brand'), fn ($query) => $query->where('brand_id', $request->integer('brand')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('is_active', $request->string('status')->toString() === 'active');
            })
            ->when($request->string('stock')->toString() === 'low', fn ($query) => $query->lowStock())
            ->when($request->string('stock')->toString() === 'out', fn ($query) => $query->outOfStock())
            ->when($request->string('stock')->toString() === 'in', fn ($query) => $query->where('current_stock', '>', 0))
            ->orderBy($request->string('sort')->toString() ?: 'name', $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc')
            ->paginate(20)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::active()->orderBy('name')->get(),
            'brands' => Brand::active()->orderBy('name')->get(),
            'summary' => $this->summaryCounts(),
        ]);
    }

    /**
     * Blank product form.
     */
    public function create(Request $request): View
    {
        $type = ProductType::tryFrom($request->string('type')->toString()) ?? ProductType::Packaged;

        return view('products.create', [
            ...$this->formData(),
            'product' => new Product([
                'type' => $type,
                'sku' => $this->suggestSku($type),
                'storage_type' => StorageType::Ambient,
                'is_active' => true,
                'price_includes_tax' => true,
                'is_weighable' => $type === ProductType::Loose,
                'tax_rate' => 0,
            ]),
        ]);
    }

    /**
     * Save a new product and its opening stock.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $this->productAttributes($request);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $data['current_stock'] = 0;

        $product = Product::create($data);

        $openingStock = (float) $request->input('opening_stock', 0);

        if ($openingStock > 0) {
            $this->inventory->recordOpeningStock(
                product: $product,
                quantity: $openingStock,
                user: $request->user(),
                expiresOn: $request->input('opening_expires_on'),
            );
        }

        return redirect()
            ->route('products.show', $product)
            ->with('status', "{$product->name} was added to the inventory.");
    }

    /**
     * Product card with live batches and the stock ledger.
     */
    public function show(Product $product): View
    {
        $product->load(['category', 'brand', 'supplier', 'unit', 'packUnit', 'creator']);

        return view('products.show', [
            'product' => $product,
            'batches' => $product->batches()->inStock()->orderByRaw('expires_on is null, expires_on asc')->get(),
            'movements' => $product->movements()->with('user')->latestFirst()->limit(25)->get(),
        ]);
    }

    /**
     * Product edit form.
     */
    public function edit(Product $product): View
    {
        return view('products.edit', [
            ...$this->formData(),
            'product' => $product,
        ]);
    }

    /**
     * Update the product master. Stock levels are untouched here.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $this->productAttributes($request, $product);
        $data['updated_by'] = $request->user()->id;

        $product->update($data);

        return redirect()
            ->route('products.show', $product)
            ->with('status', "{$product->name} was updated.");
    }

    /**
     * Archive a product. Super admins only — the stock ledger is kept intact.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('delete-records');

        if ((float) $product->current_stock > 0) {
            return back()->with('error', "{$product->name} still holds stock. Write it off with an adjustment first.");
        }

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('status', "{$product->name} was removed from the catalogue.");
    }

    /**
     * Quick switch between active and inactive without opening the form.
     */
    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('manage-products');

        $product->update([
            'is_active' => ! $product->is_active,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', sprintf(
            '%s is now %s.',
            $product->name,
            $product->is_active ? 'active' : 'inactive',
        ));
    }

    /**
     * Typeahead used by the stock entry and adjustment line items.
     */
    public function lookup(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['unit', 'packUnit'])
            ->active()
            ->search($request->string('q')->toString() ?: null)
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->display_name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'unit' => $product->unit?->code,
                'type' => $product->type->value,
                'current_stock' => (float) $product->current_stock,
                'cost_price' => (float) $product->cost_price,
                'selling_price' => (float) $product->selling_price,
                'tax_rate' => (float) $product->tax_rate,
                'track_expiry' => $product->track_expiry,
                'track_batches' => $product->track_batches,
            ]);

        return response()->json($products);
    }

    /**
     * Shared select options for the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formData(): array
    {
        return [
            'categories' => Category::active()->orderBy('name')->get(),
            'brands' => Brand::active()->orderBy('name')->get(),
            'units' => Unit::active()->orderBy('name')->get(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'types' => ProductType::cases(),
            'storageTypes' => StorageType::cases(),
            'taxRates' => config('inventory.tax_rates'),
        ];
    }

    /**
     * Map validated input onto product columns, handling the image upload.
     *
     * @return array<string, mixed>
     */
    protected function productAttributes(StoreProductRequest $request, ?Product $product = null): array
    {
        $data = $request->safe()->except(['image', 'opening_stock', 'opening_expires_on']);

        if ($request->hasFile('image')) {
            if ($product?->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }

            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        if ($data['type'] === ProductType::Loose->value) {
            $data['pack_size'] = null;
            $data['pack_unit_id'] = null;
        } else {
            $data['tare_weight'] = null;
        }

        return $data;
    }

    /**
     * Suggest the next SKU so the form is ready to type into.
     */
    protected function suggestSku(ProductType $type): string
    {
        $prefix = $type === ProductType::Loose ? 'LSE' : 'PKT';
        $lastId = Product::withTrashed()->max('id') ?? 0;

        return sprintf('%s-%05d', $prefix, $lastId + 1);
    }

    /**
     * @return array<string, int>
     */
    protected function summaryCounts(): array
    {
        return [
            'total' => Product::count(),
            'packaged' => Product::where('type', ProductType::Packaged)->count(),
            'loose' => Product::where('type', ProductType::Loose)->count(),
            'low' => Product::lowStock()->count(),
            'out' => Product::outOfStock()->count(),
        ];
    }
}
