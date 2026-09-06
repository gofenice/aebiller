@php($symbol = config('inventory.currency_symbol'))

<x-layouts.app title="Income & expenses">
    <x-page-header title="Income & expenses"
        description="Pick a period and see what the shop took, what it spent and what it kept.">
        <x-slot:actions>
            <x-button :href="route('sales.index', ['from' => $from->toDateString(), 'to' => $to->toDateString()])"
                variant="secondary" size="sm">Bills in period</x-button>
            <x-button :href="route('expenses.index', ['from' => $from->toDateString(), 'to' => $to->toDateString()])"
                variant="secondary" size="sm">Expenses in period</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-6">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-4">
            @foreach ($presets as $key => $label)
                <x-button :href="route('reports.profit-loss', ['period' => $key])"
                    :variant="$preset === $key ? 'subtle' : 'ghost'" size="sm">{{ $label }}</x-button>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3 p-4">
            <div>
                <label for="from" class="mb-1 block text-xs font-medium text-slate-500">From</label>
                <x-input type="date" name="from" id="from" value="{{ $from->toDateString() }}" />
            </div>
            <div>
                <label for="to" class="mb-1 block text-xs font-medium text-slate-500">To</label>
                <x-input type="date" name="to" id="to" value="{{ $to->toDateString() }}" />
            </div>
            <x-button type="submit" variant="secondary">Apply</x-button>
            <p class="ml-auto text-xs text-slate-500">
                {{ $from->format('j M Y') }} &ndash; {{ $to->format('j M Y') }} · {{ $days }} day(s)
            </p>
        </form>
    </x-card>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Sales (excl. VAT)" :value="$symbol.number_format($sales['revenue'], 2)"
            :sub="number_format($sales['bills']).' bill(s) · '.$symbol.number_format($sales['gross'], 2).' with VAT'"
            tone="green" icon="▮" />
        <x-stat-card label="Gross profit" :value="$symbol.number_format($grossProfit, 2)"
            :sub="'After '.$symbol.number_format($sales['cogs'], 2).' cost of goods · '.$margins['gross'].'%'"
            tone="blue" icon="↗" />
        <x-stat-card label="Operating expenses" :value="$symbol.number_format($operatingExpenses, 2)"
            :sub="'Excludes goods bought for resale'" tone="red" icon="¤" />
        <x-stat-card label="Net profit" :value="$symbol.number_format($netProfit, 2)"
            :sub="$margins['net'].'% of sales'" :tone="$netProfit >= 0 ? 'green' : 'red'" icon="◈" />
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <x-card title="Profit and loss" description="Everything net of VAT.">
                <table class="min-w-full divide-y divide-slate-200">
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="table-cell font-medium text-slate-800">Sales, excluding VAT</td>
                            <td class="table-cell text-right font-semibold text-slate-900">@money($sales['revenue'])</td>
                        </tr>
                        @if ($sales['discounts'] > 0)
                            <tr>
                                <td class="table-cell pl-8 text-slate-500">Discounts already given at the till</td>
                                <td class="table-cell text-right text-slate-500">&minus;@money($sales['discounts'])</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="table-cell text-slate-600">Cost of goods sold</td>
                            <td class="table-cell text-right text-red-600">&minus;@money($sales['cogs'])</td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="table-cell font-semibold text-slate-900">Gross profit</td>
                            <td class="table-cell text-right font-semibold text-slate-900">
                                @money($grossProfit)
                                <span class="ml-1 text-xs font-normal text-slate-500">{{ $margins['gross'] }}%</span>
                            </td>
                        </tr>
                        @forelse ($expenseCategories as $row)
                            <tr>
                                <td class="table-cell pl-8 text-slate-600">
                                    {{ $row->name }}
                                    <span class="text-xs text-slate-400">· {{ $row->entries }} entry(s)</span>
                                </td>
                                <td class="table-cell text-right text-red-600">&minus;@money($row->net)</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="table-cell pl-8 text-slate-400" colspan="2">No overheads recorded in this period.</td>
                            </tr>
                        @endforelse
                        <tr>
                            <td class="table-cell font-medium text-slate-800">Operating expenses</td>
                            <td class="table-cell text-right font-semibold text-red-600">&minus;@money($operatingExpenses)</td>
                        </tr>
                        <tr class="{{ $netProfit >= 0 ? 'bg-emerald-50' : 'bg-red-50' }}">
                            <td class="table-cell text-base font-semibold text-slate-900">Net profit</td>
                            <td class="table-cell text-right text-base font-semibold {{ $netProfit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                @money($netProfit)
                                <span class="ml-1 text-xs font-normal text-slate-500">{{ $margins['net'] }}%</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">
                    Stock bought for resale is charged here as cost of goods sold when it leaves the shelf, so the
                    supplier bills booked under &ldquo;Goods purchase&rdquo; are kept out of operating expenses —
                    counting both would subtract the same money twice.
                </p>
            </x-card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-card title="Cash out on stock" description="Goods bought for resale in this period.">
                <dl class="divide-y divide-slate-100">
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-slate-600">Supplier bills</dt>
                        <dd class="text-sm font-semibold text-slate-900">@money($goodsPurchases['total'])</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-slate-600">Of which VAT</dt>
                        <dd class="text-sm text-slate-600">@money($goodsPurchases['vat'])</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-slate-600">Entries</dt>
                        <dd class="text-sm text-slate-600">{{ number_format($goodsPurchases['entries']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-slate-600">Still unpaid <span class="text-xs text-slate-400">(all expenses)</span></dt>
                        <dd class="text-sm font-semibold {{ $expenseTotals['unpaid'] > 0 ? 'text-amber-600' : 'text-slate-600' }}">
                            @money($expenseTotals['unpaid'])
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="VAT position" description="Output VAT taken less input VAT paid.">
                <dl class="divide-y divide-slate-100">
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-slate-600">VAT charged on sales</dt>
                        <dd class="text-sm font-semibold text-slate-900">@money($sales['vat'])</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-sm text-slate-600">VAT paid on purchases</dt>
                        <dd class="text-sm text-slate-600">&minus;@money($expenseTotals['vat'])</dd>
                    </div>
                    <div class="flex items-center justify-between bg-slate-50 px-5 py-3">
                        <dt class="text-sm font-semibold text-slate-900">{{ $vatPosition >= 0 ? 'Payable to ZATCA' : 'Reclaimable' }}</dt>
                        <dd class="text-sm font-semibold {{ $vatPosition >= 0 ? 'text-slate-900' : 'text-emerald-700' }}">
                            @money(abs($vatPosition))
                        </dd>
                    </div>
                </dl>
                <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">
                    An indication only — it counts every expense recorded in the period, whatever the filing date.
                </p>
            </x-card>
        </div>
    </div>

    <x-card class="mt-6" title="{{ $groupedByMonth ? 'Month by month' : 'Day by day' }}"
        description="Sales excluding VAT, overheads, and what was left after both cost of goods and overheads.">
        @if ($series->isEmpty())
            <x-empty-state icon="↗" title="Nothing in this period"
                description="No bills were taken and no expenses were recorded between these dates." />
        @else
            @php($peak = max(1, (float) $series->max(fn (array $row): float => max($row['revenue'], $row['expenses']))))
            <div class="max-h-[28rem] overflow-y-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr>
                            <th class="table-head">{{ $groupedByMonth ? 'Month' : 'Day' }}</th>
                            <th class="table-head text-right">Sales</th>
                            <th class="table-head text-right">Overheads</th>
                            <th class="table-head text-right">Net</th>
                            <th class="table-head w-1/3">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($series as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell font-medium text-slate-800">{{ $row['label'] }}</td>
                                <td class="table-cell text-right text-slate-900">@money($row['revenue'])</td>
                                <td class="table-cell text-right text-slate-600">@money($row['expenses'])</td>
                                <td class="table-cell text-right font-semibold {{ $row['profit'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    @money($row['profit'])
                                </td>
                                <td class="table-cell">
                                    <div class="space-y-1">
                                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full bg-emerald-500"
                                                style="width: {{ round($row['revenue'] / $peak * 100, 1) }}%"></div>
                                        </div>
                                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full bg-red-400"
                                                style="width: {{ round($row['expenses'] / $peak * 100, 1) }}%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
