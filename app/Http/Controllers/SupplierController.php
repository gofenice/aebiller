<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        return view('suppliers.index', [
            'suppliers' => Supplier::query()
                ->withCount('products')
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $term = $request->string('search')->toString();
                    $query->where(function ($query) use ($term): void {
                        $query->where('name', 'like', "%{$term}%")
                            ->orWhere('code', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    });
                })
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('suppliers.form', [
            'supplier' => new Supplier([
                'code' => $this->suggestCode(),
                'is_active' => true,
                'payment_terms_days' => 0,
            ]),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return redirect()->route('suppliers.index')->with('status', "Supplier \"{$supplier->name}\" was added.");
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.form', ['supplier' => $supplier]);
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')->with('status', "Supplier \"{$supplier->name}\" was updated.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete-records');

        if ($supplier->products()->exists() || $supplier->stockEntries()->exists()) {
            return back()->with('error', "\"{$supplier->name}\" has products or stock entries linked to it. Mark it inactive instead.");
        }

        $supplier->delete();

        return back()->with('status', "Supplier \"{$supplier->name}\" was deleted.");
    }

    protected function suggestCode(): string
    {
        return sprintf('SUP-%04d', (Supplier::max('id') ?? 0) + 1);
    }
}
