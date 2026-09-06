<x-layouts.app title="Stock ledger">
    <x-page-header title="Stock ledger" description="Every quantity change, with the balance it left behind." />

    <x-card>
        <form method="GET" class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <x-input type="search" name="search" value="{{ request('search') }}" placeholder="Product or reference…" />
            </div>
            <x-select name="category">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-select>
            <x-select name="type">
                <option value="">All movement types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-select>
            <x-select name="direction">
                <option value="">In and out</option>
                <option value="in" @selected(request('direction') === 'in')>Inward only</option>
                <option value="out" @selected(request('direction') === 'out')>Outward only</option>
            </x-select>
            <div class="flex gap-2">
                <x-input type="date" name="from" value="{{ request('from') }}" class="flex-1" />
                <x-input type="date" name="to" value="{{ request('to') }}" class="flex-1" />
                <x-button type="submit" variant="secondary">Go</x-button>
            </div>
        </form>

        @if ($movements->isEmpty())
            <x-empty-state icon="≡" title="No movements found"
                description="Once stock is received or adjusted, every change is listed here." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">When</th>
                            <th class="table-head">Product</th>
                            <th class="table-head">Type</th>
                            <th class="table-head">Reference</th>
                            <th class="table-head text-right">In</th>
                            <th class="table-head text-right">Out</th>
                            <th class="table-head text-right">Balance</th>
                            <th class="table-head">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($movements as $movement)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell text-slate-600">
                                    {{ $movement->moved_at->format('d M Y') }}
                                    <span class="block text-xs text-slate-400">{{ $movement->moved_at->format('g:i a') }}</span>
                                </td>
                                <td class="table-cell">
                                    <a href="{{ route('products.show', $movement->product) }}" class="font-medium text-slate-900 hover:text-brand-700">
                                        {{ $movement->product->display_name }}
                                    </a>
                                    <span class="block font-mono text-xs text-slate-400">{{ $movement->product->sku }}</span>
                                </td>
                                <td class="table-cell">
                                    <x-badge :color="$movement->isInward() ? 'green' : 'red'">{{ $movement->type->label() }}</x-badge>
                                </td>
                                <td class="table-cell font-mono text-xs text-slate-500">
                                    {{ $movement->reference ?? '—' }}
                                    @if ($movement->batch?->batch_number)
                                        <span class="block">batch {{ $movement->batch->batch_number }}</span>
                                    @endif
                                </td>
                                <td class="table-cell text-right font-medium text-emerald-600">
                                    {{ $movement->isInward() ? rtrim(rtrim(number_format((float) $movement->quantity, 3), '0'), '.') : '' }}
                                </td>
                                <td class="table-cell text-right font-medium text-red-600">
                                    {{ $movement->isInward() ? '' : rtrim(rtrim(number_format((float) $movement->quantity, 3), '0'), '.') }}
                                </td>
                                <td class="table-cell text-right font-semibold text-slate-800">
                                    @qty($movement->balance_after) <span class="text-xs font-normal text-slate-400">{{ $movement->product->unit?->code }}</span>
                                </td>
                                <td class="table-cell text-slate-500">{{ $movement->user?->name ?? 'System' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $movements->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
