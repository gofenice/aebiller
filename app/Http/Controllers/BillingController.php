<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Services\BillingService;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class BillingController extends Controller
{
    public function __construct(
        protected BillingService $billing,
        protected InventoryService $inventory,
    ) {}

    /**
     * The till screen.
     */
    public function create(): View
    {
        $this->authorize('run-till');

        return view('billing.index', [
            'paymentMethods' => PaymentMethod::cases(),
            'nextInvoice' => $this->inventory->nextReference(Sale::class, 'INV', 'invoice_no'),
            'quickPicks' => Product::query()
                ->with('unit')
                ->active()
                ->where('current_stock', '>', 0)
                ->orderByDesc('is_weighable')
                ->orderBy('name')
                ->limit(12)
                ->get(),
            'categories' => Category::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Take payment: write the bill and take the goods out of stock.
     */
    public function store(StoreSaleRequest $request): RedirectResponse
    {
        try {
            $sale = $this->billing->createSale(
                attributes: $request->safe()->except('items'),
                lines: $request->validated('items'),
                cashier: $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('sales.show', $sale)
            ->with('status', "Bill {$sale->invoice_no} completed.");
    }

    /**
     * Barcode scan or typed product code. An exact hit on the barcode or SKU
     * is returned on its own so the till can add it without a second keystroke.
     */
    public function scan(Request $request): JsonResponse
    {
        $this->authorize('run-till');

        $code = trim($request->string('code')->toString());

        if ($code === '') {
            return response()->json(['match' => null, 'results' => []]);
        }

        $exact = Product::with('unit')
            ->active()
            ->where(fn ($query) => $query->where('barcode', $code)->orWhere('sku', $code))
            ->first();

        // Names that start with what was typed come first, so "toma" offers
        // Tomato before it offers Tide Automatic.
        $results = Product::with('unit')
            ->active()
            ->search($code)
            ->when($exact !== null, fn ($query) => $query->whereKeyNot($exact->id))
            ->orderByRaw('case when sku like ? or barcode like ? then 0 when name like ? then 1 else 2 end', ["{$code}%", "{$code}%", "{$code}%"])
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn (Product $product): array => $this->present($product));

        // An exact barcode or SKU hit always heads the list, and is the one the
        // till adds straight away when the scanner sends its Enter.
        if ($exact !== null) {
            $results->prepend($this->present($exact));
        }

        return response()->json([
            'match' => $exact !== null
                ? $this->present($exact)
                : ($results->count() === 1 ? $results->first() : null),
            'results' => $results->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->display_name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'unit' => $product->unit?->code,
            'is_weighable' => $product->is_weighable,
            'min_sale_quantity' => $product->min_sale_quantity !== null ? (float) $product->min_sale_quantity : null,
            'current_stock' => (float) $product->current_stock,
            'unit_price' => (float) $product->selling_price,
            'vat_rate' => (float) $product->tax_rate,
            'price_includes_tax' => $product->price_includes_tax,
        ];
    }
}
