<x-layouts.app title="Products">
    <x-page-header title="Products" description="Everything the store stocks — packet goods and loose produce.">
        <x-slot:actions>
            <x-button :href="route('products.create', ['type' => 'loose'])" variant="secondary">+ Loose item</x-button>
            <x-button :href="route('products.create')">+ New product</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <x-stat-card label="All products" :value="number_format($summary['total'])" icon="▤"
            :href="route('products.index')" />
        <x-stat-card label="Packet" :value="number_format($summary['packaged'])" icon="◱"
            :href="route('products.index', ['type' => 'packaged'])" />
        <x-stat-card label="Loose" :value="number_format($summary['loose'])" icon="⚖"
            :href="route('products.index', ['type' => 'loose'])" />
        <x-stat-card label="Low stock" :value="number_format($summary['low'])" tone="amber" icon="⚠"
            :href="route('products.index', ['stock' => 'low'])" />
        <x-stat-card label="Out of stock" :value="number_format($summary['out'])" tone="red" icon="✕"
            :href="route('products.index', ['stock' => 'out'])" />
    </div>

    <x-card>
        <form method="GET" action="{{ route('products.index') }}"
            class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <x-input type="search" name="search" value="{{ request('search') }}"
                    placeholder="Name, SKU or barcode…" />
            </div>

            <x-select name="category" x-on:change="$el.form.submit()">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-select>

            <x-select name="type" x-on:change="$el.form.submit()">
                <option value="">All types</option>
                @foreach (\App\Enums\ProductType::options() as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </x-select>

            <x-select name="stock" x-on:change="$el.form.submit()">
                <option value="">Any stock level</option>
                <option value="in" @selected(request('stock') === 'in')>In stock</option>
                <option value="low" @selected(request('stock') === 'low')>Low stock</option>
                <option value="out" @selected(request('stock') === 'out')>Out of stock</option>
            </x-select>

            <div class="flex gap-2">
                <x-select name="status" class="flex-1" x-on:change="$el.form.submit()">
                    <option value="">Any status</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </x-select>
                <x-button type="submit" variant="secondary">Filter</x-button>
            </div>
        </form>

        @if ($products->isEmpty())
            <x-empty-state title="No products match this view"
                description="Adjust the filters, or add the first product to get the inventory started.">
                <x-slot:actions>
                    <x-button :href="route('products.create')">+ New product</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Product</th>
                            <th class="table-head">Category</th>
                            <th class="table-head">Type</th>
                            <th class="table-head text-right">Stock</th>
                            <th class="table-head text-right">Cost</th>
                            <th class="table-head text-right">Price incl. VAT</th>
                            <th class="table-head">Status</th>
                            <th class="table-head text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($products as $product)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell">
                                    <a href="{{ route('products.show', $product) }}" class="block">
                                        <span class="font-medium text-slate-900">{{ $product->display_name }}</span>
                                        <span class="mt-0.5 block font-mono text-xs text-slate-400">
                                            {{ $product->sku }}@if ($product->barcode) · {{ $product->barcode }}@endif
                                        </span>
                                    </a>
                                </td>
                                <td class="table-cell text-slate-600">
                                    {{ $product->category?->name }}
                                    @if ($product->brand)
                                        <span class="block text-xs text-slate-400">{{ $product->brand->name }}</span>
                                    @endif
                                </td>
                                <td class="table-cell">
                                    <x-badge :color="$product->isLoose() ? 'violet' : 'blue'">
                                        {{ $product->isLoose() ? 'Loose' : 'Packet' }}
                                    </x-badge>
                                </td>
                                <td class="table-cell text-right">
                                    <span class="font-semibold text-slate-900">@qty($product->current_stock)</span>
                                    <span class="text-xs text-slate-500">{{ $product->unit?->code }}</span>
                                    @if ((float) $product->reorder_level > 0)
                                        <span class="block text-[11px] text-slate-400">min @qty($product->reorder_level)</span>
                                    @endif
                                </td>
                                <td class="table-cell text-right text-slate-600">@money($product->cost_price)</td>
                                <td class="table-cell text-right">
                                    <span class="font-medium text-slate-900">@money($product->price_including_tax)</span>
                                    <span class="block text-[11px] text-slate-400">
                                        @money($product->price_excluding_tax) + {{ rtrim(rtrim((string) $product->tax_rate, '0'), '.') }}%
                                    </span>
                                </td>
                                <td class="table-cell">
                                    <div class="flex flex-col items-start gap-1">
                                        <x-stock-badge :product="$product" />
                                        @unless ($product->is_active)
                                            <x-badge color="slate">Inactive</x-badge>
                                        @endunless
                                    </div>
                                </td>
                                <td class="table-cell text-right">
                                    <div class="flex justify-end gap-1">
                                        <x-button :href="route('products.edit', $product)" variant="ghost" size="sm">Edit</x-button>
                                        <form method="POST" action="{{ route('products.toggle', $product) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-button type="submit" variant="ghost" size="sm">
                                                {{ $product->is_active ? 'Disable' : 'Enable' }}
                                            </x-button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $products->links() }}
            </div>
        @endif
    </x-card>
</x-layouts.app>
