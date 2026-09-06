<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Models\Category;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    /**
     * The full stock ledger — every in and out, with running balance.
     */
    public function __invoke(Request $request): View
    {
        $movements = StockMovement::query()
            ->with(['product.unit', 'user', 'batch'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = $request->string('search')->toString();
                $query->where(function ($query) use ($term): void {
                    $query->where('reference', 'like', "%{$term}%")
                        ->orWhereHas('product', function ($query) use ($term): void {
                            $query->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%");
                        });
                });
            })
            ->when($request->filled('product'), fn ($query) => $query->where('product_id', $request->integer('product')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('direction'), fn ($query) => $query->where('direction', $request->string('direction')->toString()))
            ->when($request->filled('category'), function ($query) use ($request): void {
                $query->whereHas('product', fn ($query) => $query->where('category_id', $request->integer('category')));
            })
            ->when($request->filled('from'), fn ($query) => $query->whereDate('moved_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('moved_at', '<=', $request->date('to')))
            ->latestFirst()
            ->paginate(30)
            ->withQueryString();

        return view('stock-movements.index', [
            'movements' => $movements,
            'types' => MovementType::cases(),
            'categories' => Category::active()->orderBy('name')->get(),
        ]);
    }
}
