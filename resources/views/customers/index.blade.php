<x-layouts.app title="Loyalty members">
    <x-page-header title="Loyalty members"
        :description="$settings->program_name.' — '.($settings->is_enabled ? 'members earn points on every bill.' : 'the programme is switched off.')">
        <x-slot:actions>
            <x-button :href="route('customers.card-designs')" variant="secondary" target="_blank">▭ Card designs</x-button>
            <x-button type="submit" form="print-cards" variant="secondary">🖨 Print ticked cards</x-button>
            <x-button :href="route('customers.create')">+ New member</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Members" :value="number_format($totals['members'])" :sub="$totals['newThisMonth'].' joined this month'" icon="★" />
        <x-stat-card label="Shopped in 90 days" :value="number_format($totals['active'])"
            :sub="$totals['members'] > 0 ? round($totals['active'] / $totals['members'] * 100).'% of members' : null" tone="green" icon="↻" />
        <x-stat-card label="Points outstanding" :value="number_format($totals['points'])" sub="Not yet redeemed" tone="blue" icon="✦" />
        <x-stat-card label="Owed in points" :value="config('inventory.currency_symbol').number_format($totals['liability'], 2)"
            sub="If every point were redeemed today" tone="amber" icon="¤" />
    </div>

    {{-- The checkboxes in the table belong to this form, so ticking members and
         pressing "Print ticked cards" opens just their cards. --}}
    <form id="print-cards" method="GET" action="{{ route('customers.cards') }}" target="_blank"></form>

    <x-card>
        <form method="GET" class="flex flex-wrap gap-3 border-b border-slate-200 p-4">
            <x-input type="search" name="search" value="{{ request('search') }}" placeholder="Name, mobile or card number…" class="max-w-xs" />
            <x-select name="tier" class="w-40">
                <option value="">All tiers</option>
                @foreach ($tiers as $tier)
                    <option value="{{ $tier->id }}" @selected(request('tier') == $tier->id)>{{ $tier->name }} ({{ $tier->customers_count }})</option>
                @endforeach
            </x-select>
            <x-select name="status" class="w-48">
                <option value="">Any status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="lapsed" @selected(request('status') === 'lapsed')>Not seen for 90 days</option>
                <option value="on_hold" @selected(request('status') === 'on_hold')>On hold</option>
            </x-select>
            <x-select name="sort" class="w-44">
                <option value="">Sort by name</option>
                <option value="points" @selected(request('sort') === 'points')>Most points</option>
                <option value="spend" @selected(request('sort') === 'spend')>Highest spend</option>
                <option value="recent" @selected(request('sort') === 'recent')>Last visit</option>
                <option value="newest" @selected(request('sort') === 'newest')>Newest members</option>
            </x-select>
            <x-button type="submit" variant="secondary">Filter</x-button>
        </form>

        @if ($customers->isEmpty())
            <x-empty-state icon="★" title="No members found"
                description="Sign customers up here or straight from the till — they get a card number and the welcome bonus at once.">
                <x-slot:actions><x-button :href="route('customers.create')">+ New member</x-button></x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head w-8"><span class="sr-only">Print</span></th>
                            <th class="table-head">Member</th>
                            <th class="table-head">Mobile</th>
                            <th class="table-head">Tier</th>
                            <th class="table-head text-right">Points</th>
                            <th class="table-head text-right">Spend</th>
                            <th class="table-head">Last visit</th>
                            <th class="table-head">Status</th>
                            <th class="table-head text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($customers as $customer)
                            <tr class="hover:bg-slate-50/70">
                                <td class="table-cell">
                                    @if ($customer->activeCard)
                                        <input type="checkbox" name="customers[]" value="{{ $customer->id }}" form="print-cards"
                                            class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                            aria-label="Print {{ $customer->name }}'s card">
                                    @endif
                                </td>
                                <td class="table-cell">
                                    <a href="{{ route('customers.show', $customer) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $customer->name }}</a>
                                    <span class="block font-mono text-xs text-slate-400">{{ $customer->activeCard?->formattedNumber() ?? 'No active card' }}</span>
                                </td>
                                <td class="table-cell text-slate-600">{{ $customer->formattedPhone() }}</td>
                                <td class="table-cell">
                                    @if ($customer->tier)
                                        <x-badge :color="$customer->tier->card_theme->badgeColor()">{{ $customer->tier->name }}</x-badge>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="table-cell text-right">
                                    <span class="font-semibold text-slate-900">{{ number_format($customer->points_balance) }}</span>
                                    <span class="block text-xs text-slate-400">@money($customer->pointsValue($settings))</span>
                                </td>
                                <td class="table-cell text-right text-slate-600">
                                    @money($customer->lifetime_spend)
                                    <span class="block text-xs text-slate-400">{{ $customer->visits }} visit(s)</span>
                                </td>
                                <td class="table-cell text-slate-600">{{ $customer->last_visit_at?->diffForHumans() ?? 'Not yet' }}</td>
                                <td class="table-cell">
                                    <x-badge :color="$customer->is_active ? 'green' : 'amber'">{{ $customer->is_active ? 'Active' : 'On hold' }}</x-badge>
                                </td>
                                <td class="table-cell text-right">
                                    <div class="flex justify-end gap-1">
                                        <x-button :href="route('customers.show', $customer)" variant="ghost" size="sm">View</x-button>
                                        @if ($customer->activeCard)
                                            <x-button :href="route('customers.card', $customer)" variant="ghost" size="sm" target="_blank">Card</x-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $customers->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
