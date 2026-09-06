@php($symbol = config('inventory.currency_symbol'))

<x-layouts.app title="Expenses">
    <x-page-header title="Expenses" description="Every riyal going out of the shop, and what it went on.">
        <x-slot:actions>
            <x-button :href="route('expense-categories.index')" variant="secondary" size="sm">Categories</x-button>
            <x-button :href="route('expenses.create')">+ Record expense</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="{{ $showingUnpaid ? 'Unpaid total' : 'Spent in this period' }}"
            :value="$symbol.number_format((float) $summary->total, 2)" icon="¤" tone="red" />
        <x-stat-card label="Entries" :value="number_format((int) $summary->entries)" icon="▤" />
        <x-stat-card label="Input VAT" :value="$symbol.number_format((float) $summary->vat, 2)"
            sub="Reclaimable on your VAT return" icon="≡" tone="blue" />
        <x-stat-card label="Still owed" :value="$symbol.number_format($outstandingTotal, 2)"
            :sub="$outstandingCount ? $outstandingCount.' unpaid bill(s), any date' : 'Everything is settled'"
            :tone="$outstandingTotal > 0 ? 'amber' : 'green'" icon="⚠"
            :href="route('expenses.index', ['status' => 'unpaid'])" />
    </div>

    <div class="grid gap-6 2xl:grid-cols-4">
        <div class="2xl:col-span-3">
            <x-card>
                <form method="GET" class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-input type="search" name="search" value="{{ request('search') }}"
                        placeholder="Description, reference or bill no…" />

                    <x-select name="category">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </x-select>

                    <x-select name="status">
                        <option value="">Paid and unpaid</option>
                        <option value="paid" @selected(request('status') === 'paid')>Paid only</option>
                        <option value="unpaid" @selected(request('status') === 'unpaid')>Unpaid only</option>
                    </x-select>

                    <div class="flex items-center gap-2 sm:col-span-2">
                        <x-input type="date" name="from" value="{{ $from }}" class="flex-1" />
                        <span class="shrink-0 text-xs text-slate-400">to</span>
                        <x-input type="date" name="to" value="{{ $to }}" class="flex-1" />
                    </div>

                    <div class="flex gap-2">
                        <x-button type="submit" variant="secondary" class="flex-1">Filter</x-button>
                        @if (request()->hasAny(['search', 'category', 'status', 'from', 'to']))
                            <x-button :href="route('expenses.index')" variant="ghost">Reset</x-button>
                        @endif
                    </div>
                </form>

                @if ($expenses->isEmpty())
                    <x-empty-state icon="¤" title="No expenses in this period"
                        description="Record the shop's outgoings — supplier bills, staff tea, travel, repairs.">
                        <x-slot:actions><x-button :href="route('expenses.create')">+ Record expense</x-button></x-slot:actions>
                    </x-empty-state>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="table-head">Reference</th>
                                    <th class="table-head">Date</th>
                                    <th class="table-head">Description</th>
                                    <th class="table-head">Category</th>
                                    <th class="table-head">Paid to</th>
                                    <th class="table-head text-right">VAT</th>
                                    <th class="table-head text-right">Total</th>
                                    <th class="table-head">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($expenses as $expense)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="table-cell">
                                            <a href="{{ route('expenses.show', $expense) }}"
                                                class="font-mono font-medium text-brand-700 hover:underline">{{ $expense->reference_no }}</a>
                                        </td>
                                        <td class="table-cell text-slate-600">{{ $expense->expense_date->format('d M Y') }}</td>
                                        <td class="table-cell">
                                            <span class="text-slate-800">{{ $expense->description }}</span>
                                            @if ($expense->attachment_path)
                                                <span class="ml-1 text-xs text-slate-400" title="Receipt attached">◧</span>
                                            @endif
                                        </td>
                                        <td class="table-cell"><x-badge color="slate">{{ $expense->category?->name }}</x-badge></td>
                                        <td class="table-cell text-slate-600">{{ $expense->paid_to }}</td>
                                        <td class="table-cell text-right text-slate-600">@money($expense->vat_amount)</td>
                                        <td class="table-cell text-right font-semibold text-slate-900">@money($expense->total)</td>
                                        <td class="table-cell">
                                            <x-badge :color="$expense->is_paid ? 'green' : 'amber'">
                                                {{ $expense->is_paid ? 'Paid' : 'Owed' }}
                                            </x-badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-200 px-4 py-3">{{ $expenses->links() }}</div>
                @endif
            </x-card>
        </div>

        <x-card title="Where it went" :description="$from && $to ? $from.' to '.$to : 'All dates'">
            @if ($byCategory->isEmpty())
                <x-empty-state icon="⌗" title="Nothing spent yet" />
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($byCategory as $row)
                        <li class="px-5 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="truncate text-sm text-slate-700">{{ $row->name }}</span>
                                <span class="shrink-0 text-sm font-semibold text-slate-900">@money($row->total)</span>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between text-xs text-slate-500">
                                <span>{{ $row->entries }} entr{{ $row->entries === 1 ? 'y' : 'ies' }}</span>
                                @if ((float) $summary->total > 0)
                                    <span>{{ round(($row->total / $summary->total) * 100) }}%</span>
                                @endif
                            </div>
                            @if ((float) $summary->total > 0)
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-brand-500"
                                        style="width: {{ min(100, round(($row->total / $summary->total) * 100, 1)) }}%"></div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.app>
