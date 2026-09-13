<x-layouts.platform title="Billing">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">Billing</h1>
            <p class="mt-1 text-sm text-slate-500">
                Monthly subscription invoices.
                @if ($overdueCount > 0)
                    <span class="font-medium text-red-600">{{ $overdueCount }} overdue.</span>
                @endif
            </p>
        </div>
        <form method="POST" action="{{ route('platform.billing.generate') }}">
            @csrf
            <x-button type="submit" variant="secondary">Raise invoices due today</x-button>
        </form>
    </div>

    @if ($byCurrency->isNotEmpty())
        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($byCurrency as $row)
                @php $owed = (float) $row->billed - (float) $row->collected; @endphp
                <x-stat-card :label="$row->currency_code.' billed'"
                    :value="$row->currency_code.' '.number_format((float) $row->billed, 2)"
                    :sub="'Collected '.number_format((float) $row->collected, 2).' · owed '.number_format($owed, 2)"
                    :tone="$owed > 0 ? 'amber' : 'green'" icon="¤" />
            @endforeach
        </div>
    @endif

    <x-card>
        <form method="GET" class="flex flex-wrap gap-3 border-b border-slate-200 p-4">
            <x-select name="store" class="w-52">
                <option value="">All stores</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->slug }}" @selected(request('store') === $store->slug)>{{ $store->name }}</option>
                @endforeach
            </x-select>
            <x-select name="status" class="w-40">
                <option value="">Any status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-select>
            <x-input type="date" name="from" value="{{ request('from') }}" class="w-40" />
            <x-input type="date" name="to" value="{{ request('to') }}" class="w-40" />
            <x-button type="submit" variant="secondary">Filter</x-button>
        </form>

        @if ($invoices->isEmpty())
            <x-empty-state icon="¤" title="No invoices yet"
                description="Invoices are raised automatically on each store's billing day." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Invoice</th>
                            <th class="table-head">Store</th>
                            <th class="table-head">Period</th>
                            <th class="table-head">Due</th>
                            <th class="table-head text-right">Amount</th>
                            <th class="table-head text-right">Outstanding</th>
                            <th class="table-head">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($invoices as $invoice)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell">
                                    <a href="{{ route('platform.billing.show', $invoice) }}" class="font-mono text-xs font-medium text-slate-900 hover:text-brand-700">{{ $invoice->number }}</a>
                                </td>
                                <td class="table-cell">
                                    <a href="{{ route('platform.stores.show', $invoice->store) }}" class="text-slate-800 hover:text-brand-700">{{ $invoice->store?->name ?? '—' }}</a>
                                </td>
                                <td class="table-cell text-slate-600">{{ $invoice->periodLabel() }}</td>
                                <td class="table-cell text-slate-600">{{ $invoice->due_on->format('d M Y') }}</td>
                                <td class="table-cell text-right font-semibold text-slate-900">{{ $invoice->currency_code }} {{ number_format((float) $invoice->amount, 2) }}</td>
                                <td class="table-cell text-right {{ $invoice->outstanding() > 0 ? 'text-red-600' : 'text-slate-400' }}">
                                    {{ number_format($invoice->outstanding(), 2) }}
                                </td>
                                <td class="table-cell"><x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $invoices->links() }}</div>
        @endif
    </x-card>
</x-layouts.platform>
