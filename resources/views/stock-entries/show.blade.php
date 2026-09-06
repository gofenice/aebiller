<x-layouts.app :title="$entry->reference_no">
    <x-page-header :title="$entry->reference_no"
        :description="$entry->type->label().' · '.$entry->entry_date->format('d M Y')"
        :back="route('stock-entries.index')">
        <x-slot:actions>
            @can('delete-records')
                <form method="POST" action="{{ route('stock-entries.destroy', $entry) }}"
                    onsubmit="return confirm('Reverse {{ $entry->reference_no }}? The received quantities will be taken back out of stock.')">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger" size="sm">Reverse entry</x-button>
                </form>
            @endcan
            <x-button :href="route('stock-entries.create')" variant="secondary">+ New entry</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="lg:col-span-3">
            <x-card title="Received items">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="table-head">Product</th>
                                <th class="table-head">Batch / expiry</th>
                                <th class="table-head text-right">Qty</th>
                                <th class="table-head text-right">Free</th>
                                <th class="table-head text-right">Rate</th>
                                <th class="table-head text-right">Disc</th>
                                <th class="table-head text-right">VAT</th>
                                <th class="table-head text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($entry->items as $item)
                                <tr>
                                    <td class="table-cell">
                                        <a href="{{ route('products.show', $item->product) }}" class="font-medium text-slate-900 hover:text-brand-700">
                                            {{ $item->product->display_name }}
                                        </a>
                                        <span class="mt-0.5 block font-mono text-xs text-slate-400">{{ $item->product->sku }}</span>
                                    </td>
                                    <td class="table-cell text-xs text-slate-500">
                                        {{ $item->batch_number ?? '—' }}
                                        @if ($item->expires_on)
                                            <span class="block">exp {{ $item->expires_on->format('d M Y') }}</span>
                                        @endif
                                    </td>
                                    <td class="table-cell text-right font-medium">@qty($item->quantity) {{ $item->product->unit?->code }}</td>
                                    <td class="table-cell text-right text-slate-600">@qty($item->free_quantity)</td>
                                    <td class="table-cell text-right text-slate-600">@money($item->unit_cost)</td>
                                    <td class="table-cell text-right text-slate-600">{{ rtrim(rtrim((string) $item->discount_percent, '0'), '.') }}%</td>
                                    <td class="table-cell text-right text-slate-600">@money($item->tax_amount)</td>
                                    <td class="table-cell text-right font-semibold text-slate-900">@money($item->line_total)</td>
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
                    @php
                        $rows = [
                            'Supplier' => $entry->supplier?->name ?? '—',
                            'Invoice no.' => $entry->invoice_number ?? '—',
                            'Invoice date' => $entry->invoice_date?->format('d M Y') ?? '—',
                            'Entered by' => $entry->creator?->name ?? '—',
                            'Posted on' => $entry->created_at->format('d M Y, g:i a'),
                        ];
                    @endphp
                    @foreach ($rows as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-2.5">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card title="Totals">
                <dl class="space-y-2.5 px-5 py-4 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="font-medium">@money($entry->subtotal)</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="font-medium">− @money($entry->discount_total)</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">VAT</dt><dd class="font-medium">@money($entry->tax_total)</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Other charges</dt><dd class="font-medium">@money($entry->other_charges)</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2.5">
                        <dt class="font-semibold text-slate-900">Grand total</dt>
                        <dd class="text-base font-semibold text-brand-700">@money($entry->grand_total)</dd>
                    </div>
                </dl>
            </x-card>

            @if ($entry->notes)
                <x-card title="Notes">
                    <p class="px-5 py-4 text-sm text-slate-600">{{ $entry->notes }}</p>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
