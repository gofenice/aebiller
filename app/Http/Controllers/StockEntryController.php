<?php

namespace App\Http\Controllers;

use App\Enums\StockEntryType;
use App\Http\Requests\StoreStockEntryRequest;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class StockEntryController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    /**
     * Goods received notes and other inward documents.
     */
    public function index(Request $request): View
    {
        return view('stock-entries.index', [
            'entries' => StockEntry::query()
                ->with(['supplier', 'creator'])
                ->withCount('items')
                ->withSum('items', 'quantity')
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $term = $request->string('search')->toString();
                    $query->where(function ($query) use ($term): void {
                        $query->where('reference_no', 'like', "%{$term}%")
                            ->orWhere('invoice_number', 'like', "%{$term}%");
                    });
                })
                ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
                ->when($request->filled('supplier'), fn ($query) => $query->where('supplier_id', $request->integer('supplier')))
                ->when($request->filled('from'), fn ($query) => $query->whereDate('entry_date', '>=', $request->date('from')))
                ->when($request->filled('to'), fn ($query) => $query->whereDate('entry_date', '<=', $request->date('to')))
                ->latest('entry_date')
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'types' => StockEntryType::cases(),
        ]);
    }

    /**
     * The stock-in form with its dynamic line items.
     */
    public function create(Request $request): View
    {
        return view('stock-entries.create', [
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'types' => StockEntryType::cases(),
            'selectedType' => StockEntryType::tryFrom($request->string('type')->toString()) ?? StockEntryType::Purchase,
            'nextReference' => $this->inventory->nextReference(StockEntry::class, 'GRN'),
            'oldRows' => $this->rehydrateOldRows(),
        ]);
    }

    /**
     * Re-attach product details to the line items when validation sends the
     * form back, so the user does not have to search for them again.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rehydrateOldRows(): array
    {
        $rows = collect(old('items', []))->filter(fn ($row): bool => ! empty($row['product_id']));

        if ($rows->isEmpty()) {
            return [];
        }

        $products = Product::with('unit')->findMany($rows->pluck('product_id'))->keyBy('id');

        return $rows->map(function (array $row) use ($products): array {
            $product = $products->get((int) $row['product_id']);

            return [
                ...$row,
                'term' => $product?->display_name ?? '',
                'product' => $product === null ? null : [
                    'id' => $product->id,
                    'name' => $product->display_name,
                    'sku' => $product->sku,
                    'unit' => $product->unit?->code,
                    'current_stock' => (float) $product->current_stock,
                    'cost_price' => (float) $product->cost_price,
                    'track_batches' => $product->track_batches,
                    'track_expiry' => $product->track_expiry,
                ],
            ];
        })->values()->all();
    }

    /**
     * Post the entry into stock.
     */
    public function store(StoreStockEntryRequest $request): RedirectResponse
    {
        try {
            $entry = $this->inventory->createStockEntry(
                attributes: $request->safe()->except('items'),
                lines: $request->validated('items'),
                user: $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('stock-entries.show', $entry)
            ->with('status', "Stock entry {$entry->reference_no} was posted — {$entry->items->count()} product(s) received.");
    }

    public function show(StockEntry $stockEntry): View
    {
        $stockEntry->load(['items.product.unit', 'items.batch', 'supplier', 'creator']);

        return view('stock-entries.show', ['entry' => $stockEntry]);
    }

    /**
     * Reverse a posted entry by taking the received quantity back out of stock.
     */
    public function destroy(Request $request, StockEntry $stockEntry): RedirectResponse
    {
        $this->authorize('delete-records');

        try {
            $this->inventory->reverseStockEntry($stockEntry, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('stock-entries.index')
            ->with('status', "Stock entry {$stockEntry->reference_no} was reversed.");
    }
}
