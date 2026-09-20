<x-layouts.app title="Credit due">
    @php
        $symbol = config('inventory.currency_symbol');
        $oldest = $sales->first();
    @endphp

    <x-page-header title="Credit due"
        description="Bills closed on credit with money still to collect, oldest first.">
        <x-slot:actions>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <x-input name="search" value="{{ request('search') }}" placeholder="Bill no, name or mobile" class="w-56" />
                <x-select name="older_than" class="w-40">
                    <option value="">Any age</option>
                    @foreach ([7, 15, 30, 60, 90] as $option)
                        <option value="{{ $option }}" @selected(request('older_than') == $option)>Older than {{ $option }} days</option>
                    @endforeach
                </x-select>
                <x-button type="submit" variant="secondary">Apply</x-button>
                @if (request()->hasAny(['search', 'older_than', 'customer']))
                    <a href="{{ route('reports.credit') }}" class="text-xs font-medium text-slate-500 hover:underline">Clear</a>
                @endif
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Total owed" :value="$symbol.number_format($totalOwed, 2)"
            :sub="$byCustomer->sum('bills').' bill(s) unpaid · '.$symbol.number_format($openingTotal, 2).' carried over'" tone="red" icon="◷" />
        <x-stat-card label="Customers owing"
            :value="number_format($byCustomer->pluck('customer_id')->merge($carriedOver->pluck('id'))->unique()->count())" icon="☺" />
        <x-stat-card label="Oldest debt" :value="$oldest ? $oldest->daysOutstanding().' days' : '—'"
            :sub="$oldest?->customer?->name" tone="amber" icon="⏱" />
        <x-stat-card label="Collected today" :value="$symbol.number_format($collectedToday, 2)" tone="green" icon="✓" />
    </div>

    @if ($carriedOver->isNotEmpty())
        <x-card class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Carried over from before</h2>
                <p class="text-xs text-slate-500">Balances owed before the shop started billing here.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($carriedOver as $member)
                    <a href="{{ route('customers.show', $member) }}"
                        class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50/70">
                        <div class="min-w-0">
                            <span class="block truncate text-sm font-medium text-slate-900">{{ $member->name }}</span>
                            <span class="text-xs text-slate-500">
                                {{ $member->formattedPhone() }}
                                @if ($member->opening_due_on) · as at {{ $member->opening_due_on->format('d M Y') }} @endif
                                @if ($member->opening_due_note) · {{ $member->opening_due_note }} @endif
                            </span>
                        </div>
                        <span class="shrink-0 text-sm font-semibold text-red-600">
                            {{ $symbol }}{{ number_format((float) $member->opening_due_outstanding, 2) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </x-card>
    @endif

    @if ($byCustomer->isNotEmpty())
        <x-card class="mb-6">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">By customer</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($byCustomer as $row)
                    <a href="{{ route('reports.credit', ['customer' => $row->customer_id]) }}"
                        class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50/70">
                        <div class="min-w-0">
                            <span class="block truncate text-sm font-medium text-slate-900">{{ $row->customer?->name ?? 'Walk-in' }}</span>
                            <span class="text-xs text-slate-500">
                                {{ $row->customer?->phone }} · {{ $row->bills }} bill(s) ·
                                oldest {{ \Illuminate\Support\Carbon::parse($row->oldest_sold_at)->diffForHumans() }}
                            </span>
                        </div>
                        <span class="shrink-0 text-sm font-semibold text-red-600">{{ $symbol }}{{ number_format((float) $row->owed, 2) }}</span>
                    </a>
                @endforeach
            </div>
        </x-card>
    @endif

    <x-card>
        @if ($sales->isEmpty())
            <x-empty-state icon="✓" title="Nothing outstanding"
                description="Every credit bill has been paid off." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Bill</th>
                            <th class="table-head">Customer</th>
                            <th class="table-head">Sold</th>
                            <th class="table-head text-right">Age</th>
                            <th class="table-head text-right">Bill total</th>
                            <th class="table-head text-right">Paid</th>
                            <th class="table-head text-right">Still owed</th>
                            <th class="table-head text-right">Collect</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sales as $sale)
                            @php
                                $age = $sale->daysOutstanding();
                            @endphp
                            <tr class="hover:bg-slate-50/70 {{ $age >= 30 ? 'bg-red-50/40' : '' }}">
                                <td class="table-cell">
                                    <a href="{{ route('sales.show', $sale) }}" class="font-mono text-xs font-medium text-slate-900 hover:text-brand-700">
                                        {{ $sale->invoice_no }}
                                    </a>
                                </td>
                                <td class="table-cell">
                                    <span class="block font-medium text-slate-900">{{ $sale->customer?->name ?? $sale->customer_name ?? '—' }}</span>
                                    <span class="text-xs text-slate-400">{{ $sale->customer?->phone ?? $sale->customer_phone }}</span>
                                </td>
                                <td class="table-cell text-slate-600">{{ $sale->sold_at->format('d M Y') }}</td>
                                <td class="table-cell text-right">
                                    @if ($age >= 30)
                                        <x-badge color="red">{{ $age }}d</x-badge>
                                    @elseif ($age >= 7)
                                        <x-badge color="amber">{{ $age }}d</x-badge>
                                    @else
                                        <x-badge color="slate">{{ $age }}d</x-badge>
                                    @endif
                                </td>
                                <td class="table-cell text-right text-slate-700">@money((float) $sale->grand_total)</td>
                                <td class="table-cell text-right text-slate-500">@money((float) $sale->amount_paid)</td>
                                <td class="table-cell text-right font-semibold text-red-600">@money((float) $sale->amount_outstanding)</td>
                                <td class="table-cell text-right">
                                    <div x-data="{ open: false }" class="inline-block">
                                        <x-button type="button" size="sm" variant="secondary" x-on:click="open = !open">Take payment</x-button>

                                        <div x-show="open" x-cloak
                                            class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4"
                                            x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
                                            <form method="POST" action="{{ route('sales.credit-payments.store', $sale) }}"
                                                class="w-full max-w-sm space-y-3 rounded-xl bg-white p-5 text-left shadow-xl">
                                                @csrf
                                                <div>
                                                    <h3 class="text-sm font-semibold text-slate-900">Payment against {{ $sale->invoice_no }}</h3>
                                                    <p class="mt-0.5 text-xs text-slate-500">
                                                        {{ $sale->customer?->name ?? $sale->customer_name }} owes
                                                        {{ $symbol }}{{ number_format((float) $sale->amount_outstanding, 2) }}
                                                    </p>
                                                </div>

                                                <x-field label="Amount" name="amount">
                                                    <x-input type="number" step="0.01" min="0.01"
                                                        max="{{ (float) $sale->amount_outstanding }}" name="amount"
                                                        value="{{ (float) $sale->amount_outstanding }}" class="text-right" required />
                                                </x-field>

                                                <x-field label="Received as" name="payment_method">
                                                    <x-select name="payment_method">
                                                        @foreach ($settlementMethods as $method)
                                                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                                        @endforeach
                                                    </x-select>
                                                </x-field>

                                                <x-field label="Reference" name="reference">
                                                    <x-input name="reference" placeholder="Transfer or receipt number" />
                                                </x-field>

                                                <div class="flex justify-end gap-2 pt-1">
                                                    <x-button type="button" variant="secondary" x-on:click="open = false">Cancel</x-button>
                                                    <x-button type="submit">Record payment</x-button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $sales->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
