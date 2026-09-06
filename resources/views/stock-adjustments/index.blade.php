<x-layouts.app title="Stock adjustments">
    <x-page-header title="Stock adjustments" description="Damage, spoilage, shrinkage and physical count corrections.">
        <x-slot:actions>
            <x-button :href="route('stock-adjustments.create')">⇅ New adjustment</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="GET" class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-input type="search" name="search" value="{{ request('search') }}" placeholder="Reference…" />
            <x-select name="reason">
                <option value="">All reasons</option>
                @foreach ($reasons as $reason)
                    <option value="{{ $reason->value }}" @selected(request('reason') === $reason->value)>{{ $reason->label() }}</option>
                @endforeach
            </x-select>
            <x-input type="date" name="from" value="{{ request('from') }}" />
            <x-input type="date" name="to" value="{{ request('to') }}" />
            <x-button type="submit" variant="secondary">Filter</x-button>
        </form>

        @if ($adjustments->isEmpty())
            <x-empty-state icon="⇅" title="No adjustments recorded"
                description="Write off damaged or expired stock here so the ledger stays honest." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Reference</th>
                            <th class="table-head">Date</th>
                            <th class="table-head">Reason</th>
                            <th class="table-head text-right">Lines</th>
                            <th class="table-head text-right">Value</th>
                            <th class="table-head">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($adjustments as $adjustment)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell">
                                    <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="font-mono font-medium text-brand-700 hover:underline">
                                        {{ $adjustment->reference_no }}
                                    </a>
                                </td>
                                <td class="table-cell text-slate-600">{{ $adjustment->adjustment_date->format('d M Y') }}</td>
                                <td class="table-cell"><x-badge color="amber">{{ $adjustment->reason->label() }}</x-badge></td>
                                <td class="table-cell text-right text-slate-600">{{ $adjustment->items_count }}</td>
                                <td class="table-cell text-right font-semibold text-slate-900">@money($adjustment->total_value)</td>
                                <td class="table-cell text-slate-500">{{ $adjustment->creator?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $adjustments->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
