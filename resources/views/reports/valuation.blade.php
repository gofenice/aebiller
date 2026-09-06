<x-layouts.app title="Stock valuation">
    <x-page-header title="Stock valuation" description="What is sitting on the shelves, at cost and at retail." />

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-stat-card label="Value at cost" :value="config('inventory.currency_symbol').number_format($totals['cost'], 2)" icon="¤" />
        <x-stat-card label="Value at retail" :value="config('inventory.currency_symbol').number_format($totals['retail'], 2)" tone="green" icon="◈" />
        <x-stat-card label="Potential margin"
            :value="config('inventory.currency_symbol').number_format($totals['retail'] - $totals['cost'], 2)"
            :sub="$totals['cost'] > 0 ? round((($totals['retail'] - $totals['cost']) / $totals['cost']) * 100, 1).'% on cost' : null"
            tone="blue" icon="↗" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Products in stock">
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
                    <x-empty-state icon="▦" title="No stock on hand" />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="table-head">Product</th>
                                    <th class="table-head text-right">Qty</th>
                                    <th class="table-head text-right">Cost</th>
                                    <th class="table-head text-right">Value</th>
                                    <th class="table-head text-right">Retail</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($products as $product)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="table-cell">
                                            <a href="{{ route('products.show', $product) }}" class="font-medium text-slate-900 hover:text-brand-700">
                                                {{ $product->display_name }}
                                            </a>
                                            <span class="block text-xs text-slate-400">{{ $product->category?->name }}</span>
                                        </td>
                                        <td class="table-cell text-right">@qty($product->current_stock) <span class="text-xs text-slate-400">{{ $product->unit?->code }}</span></td>
                                        <td class="table-cell text-right text-slate-600">@money($product->cost_price)</td>
                                        <td class="table-cell text-right font-semibold text-slate-900">@money($product->stock_value)</td>
                                        <td class="table-cell text-right text-slate-600">@money((float) $product->current_stock * (float) $product->selling_price)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-200 px-4 py-3">{{ $products->links() }}</div>
                @endif
            </x-card>
        </div>

        <x-card title="By category">
            @if ($byCategory->isEmpty())
                <x-empty-state icon="⌗" title="Nothing to value yet" />
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($byCategory as $row)
                        <li class="px-5 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-slate-800">{{ $row->name }}</span>
                                <span class="text-sm font-semibold text-slate-900">@money($row->cost_value)</span>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between gap-3 text-xs text-slate-500">
                                <span>{{ $row->products }} product(s)</span>
                                <span>retail @money($row->retail_value)</span>
                            </div>
                            @if ($totals['cost'] > 0)
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-brand-500"
                                        style="width: {{ min(100, round(($row->cost_value / $totals['cost']) * 100, 1)) }}%"></div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.app>
