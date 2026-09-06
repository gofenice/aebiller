<x-layouts.app title="Stock entries">
    <x-page-header title="Stock entries" description="Every goods receipt, opening stock load and sales return.">
        <x-slot:actions>
            <x-button :href="route('stock-entries.create')">↓ New stock entry</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="GET" class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <x-input type="search" name="search" value="{{ request('search') }}" placeholder="Reference or invoice no…" />
            </div>
            <x-select name="type">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-select>
            <x-select name="supplier">
                <option value="">All suppliers</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(request('supplier') == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </x-select>
            <x-input type="date" name="from" value="{{ request('from') }}" />
            <div class="flex gap-2">
                <x-input type="date" name="to" value="{{ request('to') }}" class="flex-1" />
                <x-button type="submit" variant="secondary">Filter</x-button>
            </div>
        </form>

        @if ($entries->isEmpty())
            <x-empty-state icon="↓" title="No stock entries yet"
                description="Record your first goods receipt to start building the stock ledger.">
                <x-slot:actions>
                    <x-button :href="route('stock-entries.create')">New stock entry</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Reference</th>
                            <th class="table-head">Date</th>
                            <th class="table-head">Type</th>
                            <th class="table-head">Supplier</th>
                            <th class="table-head">Invoice</th>
                            <th class="table-head text-right">Lines</th>
                            <th class="table-head text-right">Units</th>
                            <th class="table-head text-right">Value</th>
                            <th class="table-head">Entered by</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($entries as $entry)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell">
                                    <a href="{{ route('stock-entries.show', $entry) }}" class="font-mono font-medium text-brand-700 hover:underline">
                                        {{ $entry->reference_no }}
                                    </a>
                                </td>
                                <td class="table-cell text-slate-600">{{ $entry->entry_date->format('d M Y') }}</td>
                                <td class="table-cell"><x-badge color="blue">{{ $entry->type->label() }}</x-badge></td>
                                <td class="table-cell text-slate-700">{{ $entry->supplier?->name ?? '—' }}</td>
                                <td class="table-cell font-mono text-xs text-slate-500">{{ $entry->invoice_number ?? '—' }}</td>
                                <td class="table-cell text-right text-slate-600">{{ $entry->items_count }}</td>
                                <td class="table-cell text-right text-slate-600">@qty($entry->items_sum_quantity ?? 0)</td>
                                <td class="table-cell text-right font-semibold text-slate-900">@money($entry->grand_total)</td>
                                <td class="table-cell text-slate-500">{{ $entry->creator?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $entries->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
