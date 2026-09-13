<x-layouts.platform title="Dashboard">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">Every shop running on {{ config('tenancy.central_domain') }}.</p>
        </div>
        <x-button :href="route('platform.stores.create')">+ New store</x-button>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-stat-card label="Stores" :value="number_format($stats['stores'])" :sub="$stats['newThisMonth'].' added this month'" icon="◧" />
        <x-stat-card label="Active" :value="number_format($stats['active'])" tone="green" icon="✓" />
        <x-stat-card label="Suspended" :value="number_format($stats['suspended'])" :tone="$stats['suspended'] > 0 ? 'red' : 'slate'" icon="⏸" />
        <x-stat-card label="Bills this month" :value="number_format($stats['billsThisMonth'])" sub="Across all stores" tone="blue" icon="▮" />
        <x-stat-card label="Stores owing money" :value="number_format($stats['overdueStores'])"
            :tone="$stats['overdueStores'] > 0 ? 'red' : 'slate'" :href="route('platform.billing.index', ['status' => 'overdue'])" icon="¤" />
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-card title="Owed to us" description="Unpaid subscription invoices, by currency.">
            @if ($outstanding->isEmpty())
                <p class="px-5 py-4 text-sm text-slate-500">Nothing outstanding.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($outstanding as $row)
                        <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                            <span class="text-slate-500">{{ $row->currency_code }}</span>
                            <span class="font-semibold text-slate-900">{{ number_format((float) $row->total, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Collected this month" description="Payments recorded since the 1st.">
            @if ($collectedThisMonth->isEmpty())
                <p class="px-5 py-4 text-sm text-slate-500">Nothing recorded yet this month.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($collectedThisMonth as $row)
                        <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                            <span class="text-slate-500">{{ $row->currency_code }}</span>
                            <span class="font-semibold text-emerald-600">{{ number_format((float) $row->total, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <x-card title="Newest stores" description="The ten most recently created.">
        <x-slot:actions>
            <x-button :href="route('platform.stores.index')" variant="secondary" size="sm">All stores</x-button>
        </x-slot:actions>

        @if ($stores->isEmpty())
            <x-empty-state icon="◧" title="No stores yet"
                description="Create the first store and it gets its own address, owner sign-in and starter settings.">
                <x-slot:actions><x-button :href="route('platform.stores.create')">+ New store</x-button></x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Store</th>
                            <th class="table-head">Address</th>
                            <th class="table-head">Currency</th>
                            <th class="table-head text-right">Staff</th>
                            <th class="table-head text-right">Products</th>
                            <th class="table-head text-right">Bills this month</th>
                            <th class="table-head">Last sale</th>
                            <th class="table-head">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($stores as $store)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell">
                                    <a href="{{ route('platform.stores.show', $store) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $store->name }}</a>
                                </td>
                                <td class="table-cell font-mono text-xs text-slate-500">{{ $store->host() }}</td>
                                <td class="table-cell text-slate-600">{{ $store->currency_code }}</td>
                                <td class="table-cell text-right text-slate-600">{{ $store->users_count }}</td>
                                <td class="table-cell text-right text-slate-600">{{ $store->products_count }}</td>
                                <td class="table-cell text-right font-semibold text-slate-900">{{ $store->bills_this_month }}</td>
                                <td class="table-cell text-slate-500">
                                    {{ $store->last_sale_at ? \Illuminate\Support\Carbon::parse($store->last_sale_at)->diffForHumans() : 'No sales yet' }}
                                </td>
                                <td class="table-cell"><x-badge :color="$store->status->color()">{{ $store->status->label() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.platform>
