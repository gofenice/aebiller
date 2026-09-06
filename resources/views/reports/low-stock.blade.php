<x-layouts.app title="Low stock">
    <x-page-header title="Low stock & reorder list"
        description="Items at or below their reorder level, worst first — the shopping list for your next purchase order." />

    <x-card>
        <form method="GET" class="flex flex-wrap gap-3 border-b border-slate-200 p-4">
            <x-select name="category" class="w-56">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-select>
            <x-button type="submit" variant="secondary">Filter</x-button>
        </form>

        @if ($products->isEmpty())
            <x-empty-state icon="✓" title="Nothing to reorder"
                description="Every active product is above its reorder level." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Product</th>
                            <th class="table-head">Category</th>
                            <th class="table-head">Supplier</th>
                            <th class="table-head text-right">On hand</th>
                            <th class="table-head text-right">Reorder at</th>
                            <th class="table-head text-right">Suggested order</th>
                            <th class="table-head">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($products as $product)
                            @php
                                $target = (float) ($product->max_stock_level ?: (float) $product->reorder_level * 2);
                                $suggested = max($target - (float) $product->current_stock, 0);
                            @endphp
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell">
                                    <a href="{{ route('products.show', $product) }}" class="font-medium text-slate-900 hover:text-brand-700">
                                        {{ $product->display_name }}
                                    </a>
                                    <span class="block font-mono text-xs text-slate-400">{{ $product->sku }}</span>
                                </td>
                                <td class="table-cell text-slate-600">{{ $product->category?->name }}</td>
                                <td class="table-cell text-slate-600">{{ $product->supplier?->name ?? '—' }}</td>
                                <td class="table-cell text-right font-semibold {{ $product->isOutOfStock() ? 'text-red-600' : 'text-amber-600' }}">
                                    @qty($product->current_stock) <span class="text-xs font-normal text-slate-400">{{ $product->unit?->code }}</span>
                                </td>
                                <td class="table-cell text-right text-slate-600">@qty($product->reorder_level)</td>
                                <td class="table-cell text-right font-medium text-slate-900">
                                    @qty($suggested) <span class="text-xs font-normal text-slate-400">{{ $product->unit?->code }}</span>
                                </td>
                                <td class="table-cell"><x-stock-badge :product="$product" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $products->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
