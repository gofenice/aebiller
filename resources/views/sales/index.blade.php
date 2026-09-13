<x-layouts.app title="Bills">
    @php
        $symbol = config('inventory.currency_symbol');
    @endphp

    <x-page-header title="Bills" description="Every sale taken at the till.">
        <x-slot:actions>
            <x-button :href="route('billing.create')">+ New sale</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Sales today" :value="$symbol.number_format($todayTotal, 2)" icon="¤" tone="green" />
        <x-stat-card label="Bills in view" :value="number_format((int) $summary->bills)" icon="▤" />
        <x-stat-card label="Revenue in view" :value="$symbol.number_format((float) $summary->revenue, 2)" icon="↗" />
        <x-stat-card label="VAT collected" :value="$symbol.number_format((float) $summary->vat, 2)"
            :sub="'Gross profit '.$symbol.number_format((float) $summary->profit, 2)" icon="≡" tone="blue" />
    </div>

    <x-card>
        <form method="GET" class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <x-input type="search" name="search" value="{{ request('search') }}" placeholder="Invoice, customer or phone…" />
            </div>
            <x-select name="payment_method">
                <option value="">All payments</option>
                @foreach ($paymentMethods as $method)
                    <option value="{{ $method->value }}" @selected(request('payment_method') === $method->value)>{{ $method->shortLabel() }}</option>
                @endforeach
            </x-select>
            <x-select name="cashier">
                <option value="">All cashiers</option>
                @foreach ($cashiers as $cashier)
                    <option value="{{ $cashier->id }}" @selected(request('cashier') == $cashier->id)>{{ $cashier->name }}</option>
                @endforeach
            </x-select>
            <x-input type="date" name="from" value="{{ request('from') }}" />
            <div class="flex gap-2">
                <x-input type="date" name="to" value="{{ request('to') }}" class="flex-1" />
                <x-button type="submit" variant="secondary">Filter</x-button>
            </div>
        </form>

        @if ($sales->isEmpty())
            <x-empty-state icon="▮▯▮" title="No bills yet"
                description="Open the till and scan the first product to take a sale.">
                <x-slot:actions><x-button :href="route('billing.create')">Open the till</x-button></x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Invoice</th>
                            <th class="table-head">When</th>
                            <th class="table-head">Customer</th>
                            <th class="table-head">Payment</th>
                            <th class="table-head text-right">Items</th>
                            <th class="table-head text-right">VAT</th>
                            <th class="table-head text-right">Total</th>
                            <th class="table-head">Cashier</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sales as $sale)
                            <tr class="hover:bg-slate-50/70 {{ $sale->isVoided() ? 'bg-red-50/40' : '' }}">
                                <td class="table-cell">
                                    <a href="{{ route('sales.show', $sale) }}" class="font-mono font-medium text-brand-700 hover:underline">
                                        {{ $sale->invoice_no }}
                                    </a>
                                    @if ($sale->isVoided())
                                        <x-badge color="red" class="ml-1">Voided</x-badge>
                                    @endif
                                </td>
                                <td class="table-cell text-slate-600">
                                    {{ $sale->sold_at->format('d M Y') }}
                                    <span class="block text-xs text-slate-400">{{ $sale->sold_at->format('g:i a') }}</span>
                                </td>
                                <td class="table-cell text-slate-700">
                                    {{ $sale->customer_name ?? 'Walk-in' }}
                                    @if ($sale->customer_id)
                                        <a href="{{ route('customers.show', $sale->customer_id) }}" class="ml-0.5 text-xs text-amber-500 hover:text-amber-600"
                                            title="Loyalty member">★</a>
                                    @endif
                                    @if ($sale->customer_phone)
                                        <span class="block text-xs text-slate-400">{{ $sale->customer_phone }}</span>
                                    @endif
                                </td>
                                <td class="table-cell"><x-badge color="blue">{{ $sale->payment_method->shortLabel() }}</x-badge></td>
                                <td class="table-cell text-right text-slate-600">{{ $sale->items_count }}</td>
                                <td class="table-cell text-right text-slate-600">@money($sale->vat_total)</td>
                                <td class="table-cell text-right font-semibold text-slate-900">@money($sale->grand_total)</td>
                                <td class="table-cell text-slate-500">{{ $sale->cashier?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $sales->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
