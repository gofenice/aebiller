<x-layouts.app :title="$adjustment->reference_no">
    <x-page-header :title="$adjustment->reference_no"
        :description="$adjustment->reason->label().' · '.$adjustment->adjustment_date->format('d M Y')"
        :back="route('stock-adjustments.index')" />

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="lg:col-span-3">
            <x-card title="Adjusted items">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="table-head">Product</th>
                                <th class="table-head">Direction</th>
                                <th class="table-head text-right">Quantity</th>
                                <th class="table-head text-right">Before</th>
                                <th class="table-head text-right">After</th>
                                <th class="table-head text-right">Value</th>
                                <th class="table-head">Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($adjustment->items as $item)
                                <tr>
                                    <td class="table-cell">
                                        <a href="{{ route('products.show', $item->product) }}" class="font-medium text-slate-900 hover:text-brand-700">
                                            {{ $item->product->display_name }}
                                        </a>
                                        <span class="mt-0.5 block font-mono text-xs text-slate-400">{{ $item->product->sku }}</span>
                                    </td>
                                    <td class="table-cell">
                                        <x-badge :color="$item->isInward() ? 'green' : 'red'">
                                            {{ $item->isInward() ? 'Added' : 'Removed' }}
                                        </x-badge>
                                    </td>
                                    <td class="table-cell text-right font-semibold {{ $item->isInward() ? 'text-emerald-600' : 'text-red-600' }}">
                                        {{ $item->isInward() ? '+' : '−' }}@qty($item->quantity) {{ $item->product->unit?->code }}
                                    </td>
                                    <td class="table-cell text-right text-slate-500">@qty($item->stock_before)</td>
                                    <td class="table-cell text-right font-medium text-slate-800">@qty($item->stock_after)</td>
                                    <td class="table-cell text-right text-slate-700">@money($item->value)</td>
                                    <td class="table-cell text-xs text-slate-500">{{ $item->notes ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Summary">
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">Reason</dt>
                        <dd class="font-medium text-slate-800">{{ $adjustment->reason->label() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">Total value</dt>
                        <dd class="font-semibold text-slate-900">@money($adjustment->total_value)</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">Posted by</dt>
                        <dd class="font-medium text-slate-800">{{ $adjustment->creator?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">Posted on</dt>
                        <dd class="font-medium text-slate-800">{{ $adjustment->created_at->format('d M Y, g:i a') }}</dd>
                    </div>
                </dl>
            </x-card>

            @if ($adjustment->notes)
                <x-card title="Notes">
                    <p class="px-5 py-4 text-sm text-slate-600">{{ $adjustment->notes }}</p>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
