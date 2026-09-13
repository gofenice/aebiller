<x-layouts.app title="Loyalty report">
    @php
        $symbol = config('inventory.currency_symbol');
        $tierTotal = max($tiers->sum('customers_count'), 1);
        $basketMax = max($stats['memberBasket'], $stats['walkInBasket'], 0.01);
    @endphp

    <x-page-header title="Loyalty report"
        :description="$settings->program_name.' · '.$from->format('d M Y').' – '.$to->format('d M Y')">
        <x-slot:actions>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <x-input type="date" name="from" value="{{ $from->toDateString() }}" class="w-40" />
                <span class="text-sm text-slate-400">to</span>
                <x-input type="date" name="to" value="{{ $to->toDateString() }}" class="w-40" />
                <x-button type="submit" variant="secondary">Apply</x-button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <p class="mb-2 text-xs font-semibold tracking-wider text-slate-400 uppercase">Right now</p>
    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Members" :value="number_format($stats['members'])" :sub="$stats['newMembers'].' joined in this period'" icon="★" />
        <x-stat-card label="Shopped in 90 days" :value="number_format($stats['activeMembers'])"
            :sub="$stats['members'] > 0 ? round($stats['activeMembers'] / $stats['members'] * 100).'% of members' : null" tone="green" icon="↻" />
        <x-stat-card label="Points outstanding" :value="number_format($stats['outstanding'])"
            :sub="'Owed if all redeemed: '.$symbol.number_format($stats['liability'], 2)" tone="blue" icon="✦" />
        <x-stat-card label="Expiring in 30 days" :value="number_format($stats['expiringSoon'])" sub="A reason to invite members back" tone="amber" icon="⏱" />
    </div>

    <p class="mb-2 text-xs font-semibold tracking-wider text-slate-400 uppercase">In this period</p>
    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Points issued" :value="number_format($stats['issued'])" sub="Earned plus welcome and birthday bonuses" tone="green" />
        <x-stat-card label="Points redeemed" :value="number_format($stats['redeemed'])"
            :sub="$symbol.number_format($stats['discountGiven'], 2).' taken off bills'" tone="blue" />
        <x-stat-card label="Points expired" :value="number_format($stats['expired'])"
            :sub="$stats['adjusted'] !== 0 ? 'Manual adjustments: '.($stats['adjusted'] > 0 ? '+' : '').number_format($stats['adjusted']) : null" />
        <x-stat-card label="Member share of sales" :value="$stats['memberShare'].'%'"
            :sub="$stats['memberBills'].' of '.$stats['bills'].' bills had a card'" tone="amber" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-card title="Average bill" description="Do members spend more per visit?">
                <div class="space-y-4 p-5">
                    @foreach (['Members' => $stats['memberBasket'], 'Walk-in shoppers' => $stats['walkInBasket']] as $label => $value)
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600">{{ $label }}</span>
                                <span class="font-semibold text-slate-900">@money($value)</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $loop->first ? 'bg-brand-500' : 'bg-slate-400' }}"
                                    style="width: {{ round($value / $basketMax * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card title="Members by tier">
                <ul class="divide-y divide-slate-100">
                    @foreach ($tiers as $tier)
                        <li class="px-5 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <x-badge :color="$tier->card_theme->badgeColor()">{{ $tier->name }}</x-badge>
                                <span class="text-sm font-semibold text-slate-900">{{ number_format($tier->customers_count) }}</span>
                            </div>
                            <div class="mt-1.5 flex justify-between text-xs text-slate-500">
                                <span>from @money($tier->min_spend)</span>
                                <span>× {{ rtrim(rtrim(number_format((float) $tier->earn_multiplier, 2), '0'), '.') }} points</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-brand-500" style="width: {{ round($tier->customers_count / $tierTotal * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>

        <div class="lg:col-span-2">
            <x-card title="Top members" description="By spend in this period.">
                @if ($topMembers->isEmpty())
                    <x-empty-state icon="★" title="No member bills in this period"
                        description="Bills show up here once a card is scanned, or a mobile number typed, at the till." />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="table-head w-8">#</th>
                                    <th class="table-head">Member</th>
                                    <th class="table-head">Tier</th>
                                    <th class="table-head text-right">Visits</th>
                                    <th class="table-head text-right">Spend</th>
                                    <th class="table-head text-right">Points</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($topMembers as $member)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="table-cell text-xs text-slate-400">{{ $loop->iteration }}</td>
                                        <td class="table-cell">
                                            <a href="{{ route('customers.show', $member) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $member->name }}</a>
                                            <span class="block text-xs text-slate-400">{{ $member->formattedPhone() }}</span>
                                        </td>
                                        <td class="table-cell">
                                            @if ($member->tier)
                                                <x-badge :color="$member->tier->card_theme->badgeColor()">{{ $member->tier->name }}</x-badge>
                                            @endif
                                        </td>
                                        <td class="table-cell text-right text-slate-600">{{ $member->period_visits }}</td>
                                        <td class="table-cell text-right font-semibold text-slate-900">@money($member->period_spend)</td>
                                        <td class="table-cell text-right text-slate-600">{{ number_format($member->points_balance) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
