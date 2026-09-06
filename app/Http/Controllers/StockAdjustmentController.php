<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentReason;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class StockAdjustmentController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function index(Request $request): View
    {
        return view('stock-adjustments.index', [
            'adjustments' => StockAdjustment::query()
                ->with('creator')
                ->withCount('items')
                ->when($request->filled('search'), fn ($query) => $query->where('reference_no', 'like', '%'.$request->string('search')->toString().'%'))
                ->when($request->filled('reason'), fn ($query) => $query->where('reason', $request->string('reason')->toString()))
                ->when($request->filled('from'), fn ($query) => $query->whereDate('adjustment_date', '>=', $request->date('from')))
                ->when($request->filled('to'), fn ($query) => $query->whereDate('adjustment_date', '<=', $request->date('to')))
                ->latest('adjustment_date')
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'reasons' => AdjustmentReason::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('stock-adjustments.create', [
            'reasons' => AdjustmentReason::cases(),
            'selectedReason' => AdjustmentReason::tryFrom($request->string('reason')->toString()) ?? AdjustmentReason::Damage,
            'nextReference' => $this->inventory->nextReference(StockAdjustment::class, 'ADJ'),
            'oldRows' => $this->rehydrateOldRows(),
        ]);
    }

    /**
     * Put product details back on the line items after a failed validation.
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
                ],
            ];
        })->values()->all();
    }

    public function store(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        try {
            $adjustment = $this->inventory->createAdjustment(
                attributes: $request->safe()->except('items'),
                lines: $request->validated('items'),
                user: $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('stock-adjustments.show', $adjustment)
            ->with('status', "Adjustment {$adjustment->reference_no} was posted.");
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        $stockAdjustment->load(['items.product.unit', 'items.batch', 'creator']);

        return view('stock-adjustments.show', ['adjustment' => $stockAdjustment]);
    }
}
