<x-layouts.app :title="$customer->name">
    @php
        $card = $customer->activeCard;
        $tier = $customer->tier;
        $next = $summary['next_tier'];
    @endphp

    <x-loyalty.card-styles />

    <x-page-header :title="$customer->name"
        :description="'Member since '.$customer->created_at->format('d M Y').' · '.$customer->formattedPhone()"
        :back="route('customers.index')">
        <x-slot:actions>
            @if ($card)
                <x-button :href="route('customers.card', $customer)" variant="secondary" target="_blank">🖨 Print card</x-button>
            @endif
            <x-button :href="route('customers.edit', $customer)" variant="secondary">Edit</x-button>
            <x-button :href="route('billing.create')">▮ New sale</x-button>
        </x-slot:actions>
    </x-page-header>

    @unless ($customer->is_active)
        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            This membership is on hold: the card will not earn or redeem points until it is reactivated from <strong>Edit</strong>.
        </div>
    @endunless

    <div class="grid gap-6 xl:grid-cols-5">
        <div class="space-y-6 xl:col-span-3">
            <x-card title="Loyalty card"
                :description="$card ? 'Card '.$card->formattedNumber().' · issued '.$card->issued_at->format('d M Y') : 'No active card — issue one below.'">
                @if ($card)
                    <div class="overflow-x-auto p-5">
                        <div class="flex flex-wrap gap-5">
                            <x-loyalty.card-front :customer="$customer" :settings="$settings" />
                            <x-loyalty.card-back :customer="$customer" :settings="$settings" :barcode-svg="$barcodeSvg" :qr-svg="$qrSvg" />
                        </div>
                    </div>
                @endif

                <div class="border-t border-slate-200 px-5 py-4" x-data="{ open: {{ $card ? 'false' : 'true' }} }">
                    <button type="button" x-on:click="open = !open" class="text-xs font-medium text-slate-600 hover:text-brand-700">
                        {{ $card ? 'Card lost or damaged? Issue a replacement' : 'Issue a card' }}
                    </button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('customers.replace-card', $customer) }}"
                        class="mt-3 flex flex-wrap items-end gap-3"
                        onsubmit="return confirm('Issue a new card number? The current card will stop scanning at the till.')">
                        @csrf
                        <x-field label="Reason" name="reason">
                            <x-select name="reason" id="reason">
                                <option value="lost">Lost or stolen</option>
                                <option value="damaged">Damaged or worn out</option>
                            </x-select>
                        </x-field>
                        <x-field label="Note" name="note" class="min-w-48 flex-1">
                            <x-input name="note" id="note" placeholder="Optional" />
                        </x-field>
                        <x-button type="submit" variant="secondary">Issue new card</x-button>
                    </form>
                    <p x-show="open" x-cloak class="form-hint">The points stay with the member — only the card number changes.</p>
                </div>
            </x-card>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <x-stat-card label="Lifetime spend" :value="config('inventory.currency_symbol').number_format((float) $customer->lifetime_spend, 2)" />
                <x-stat-card label="Visits" :value="number_format($customer->visits)" />
                <x-stat-card label="Average bill" :value="config('inventory.currency_symbol').number_format($summary['average_basket'], 2)" />
                <x-stat-card label="Last visit" :value="$customer->last_visit_at?->format('d M') ?? '—'"
                    :sub="$customer->last_visit_at?->diffForHumans()" />
            </div>

            <x-card title="Points history">
                @if ($transactions->isEmpty())
                    <x-empty-state icon="✦" title="No points yet" />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="table-head">Date</th>
                                    <th class="table-head">Entry</th>
                                    <th class="table-head text-right">Points</th>
                                    <th class="table-head text-right">Balance</th>
                                    <th class="table-head">Expires</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($transactions as $transaction)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="table-cell text-slate-600">
                                            {{ $transaction->created_at->format('d M Y') }}
                                            <span class="block text-xs text-slate-400">{{ $transaction->created_at->format('g:i a') }}</span>
                                        </td>
                                        <td class="table-cell">
                                            <x-badge :color="$transaction->type->color()">{{ $transaction->type->label() }}</x-badge>
                                            <span class="mt-0.5 block max-w-xs truncate text-xs text-slate-500">
                                                @if ($transaction->sale)
                                                    <a href="{{ route('sales.show', $transaction->sale) }}" class="hover:text-brand-700">{{ $transaction->description }}</a>
                                                @else
                                                    {{ $transaction->description }}
                                                @endif
                                                @if ($transaction->user) · {{ $transaction->user->name }} @endif
                                            </span>
                                        </td>
                                        <td @class(['table-cell text-right font-semibold', 'text-emerald-600' => $transaction->isCredit(), 'text-slate-700' => ! $transaction->isCredit()])>
                                            {{ $transaction->isCredit() ? '+' : '−' }}{{ number_format(abs($transaction->points)) }}
                                        </td>
                                        <td class="table-cell text-right text-slate-600">{{ number_format($transaction->balance_after) }}</td>
                                        <td class="table-cell text-xs text-slate-500">
                                            @if ($transaction->isCredit() && $transaction->expires_at)
                                                {{ $transaction->points_remaining > 0 ? $transaction->expires_at->format('d M Y') : 'Used' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-200 px-4 py-3">{{ $transactions->links() }}</div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6 xl:col-span-2">
            <div class="card overflow-hidden">
                <div class="bg-linear-to-br from-brand-800 to-brand-500 px-5 py-5 text-white">
                    <p class="text-xs font-medium tracking-wide uppercase opacity-80">Points balance</p>
                    <p class="mt-1 text-4xl font-semibold tracking-tight">{{ number_format($customer->points_balance) }}</p>
                    <p class="mt-1 text-sm opacity-90">Worth @money($customer->pointsValue($settings)) at the till</p>
                </div>

                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex items-center justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">Tier</dt>
                        <dd>
                            @if ($tier)
                                <x-badge :color="$tier->card_theme->badgeColor()">{{ $tier->name }}</x-badge>
                                <span class="ml-1 text-xs text-slate-500">× {{ rtrim(rtrim(number_format((float) $tier->earn_multiplier, 2), '0'), '.') }} points</span>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">Spend in the last {{ $settings->tier_window_months }} months</dt>
                        <dd class="font-medium text-slate-800">@money($summary['qualifying_spend'])</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">Points ever earned</dt>
                        <dd class="font-medium text-slate-800">{{ number_format($summary['issued']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">Points redeemed</dt>
                        <dd class="font-medium text-slate-800">{{ number_format($summary['redeemed']) }}</dd>
                    </div>
                    @if ($summary['expiring_points'] > 0)
                        <div class="flex justify-between gap-4 bg-amber-50 px-5 py-2.5">
                            <dt class="text-amber-800">Expiring in 30 days</dt>
                            <dd class="font-semibold text-amber-800">
                                {{ number_format($summary['expiring_points']) }}
                                <span class="block text-right text-xs font-normal">from {{ $summary['next_expiry']?->format('d M Y') }}</span>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($next)
                    @php
                        $target = (float) $next['tier']->min_spend;
                        $progress = $target > 0 ? min(100, round($summary['qualifying_spend'] / $target * 100)) : 100;
                    @endphp
                    <div class="border-t border-slate-200 px-5 py-4">
                        <div class="flex justify-between text-xs">
                            <span class="font-medium text-slate-700">Next: {{ $next['tier']->name }}</span>
                            <span class="text-slate-500">@money($next['remaining']) to go</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-brand-500" style="width: {{ $progress }}%"></div>
                        </div>
                        @if ($next['tier']->perks)
                            <p class="form-hint">{{ $next['tier']->perks }}</p>
                        @endif
                    </div>
                @endif
            </div>

            <x-card title="Account" description="What this customer owes: the balance carried over from before, plus any credit bill still unpaid.">
                @php
                    $openingDue = (float) $customer->opening_due_outstanding;
                    $billsDue = $customer->billsDue();
                @endphp

                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">
                            Carried over from before
                            @if ($customer->opening_due_on)
                                <span class="block text-xs text-slate-400">as at {{ $customer->opening_due_on->format('d M Y') }}</span>
                            @endif
                            @if ($customer->opening_due_note)
                                <span class="block text-xs text-slate-400">{{ $customer->opening_due_note }}</span>
                            @endif
                        </dt>
                        <dd class="text-right font-medium {{ $openingDue > 0 ? 'text-red-600' : 'text-slate-800' }}">
                            @money($openingDue)
                            @if ((float) $customer->opening_due > 0 && $openingDue < (float) $customer->opening_due)
                                <span class="block text-xs font-normal text-slate-400">
                                    of @money((float) $customer->opening_due) originally
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">Unpaid credit bills</dt>
                        <dd class="text-right font-medium {{ $billsDue > 0 ? 'text-red-600' : 'text-slate-800' }}">@money($billsDue)</dd>
                    </div>
                    <div class="flex justify-between gap-4 bg-slate-50 px-5 py-2.5">
                        <dt class="font-semibold text-slate-900">Total owed</dt>
                        <dd class="text-right text-lg font-semibold {{ $customer->totalDue() > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                            @money($customer->totalDue())
                        </dd>
                    </div>
                </dl>

                @can('run-till')
                    @if ($openingDue > 0)
                        <form method="POST" action="{{ route('customers.opening-due.pay', $customer) }}"
                            class="space-y-3 border-t border-slate-200 p-5">
                            @csrf
                            <p class="text-sm font-medium text-slate-700">Take payment against the old balance</p>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <x-field label="Amount" name="amount">
                                    <x-input type="number" step="0.01" min="0.01" max="{{ $openingDue }}" name="amount"
                                        id="amount" value="{{ $openingDue }}" class="text-right" required />
                                </x-field>
                                <x-field label="Received as" name="payment_method">
                                    <x-select name="payment_method" id="payment_method">
                                        @foreach (\App\Enums\PaymentMethod::settlementMethods() as $method)
                                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                        @endforeach
                                    </x-select>
                                </x-field>
                                <x-field label="Reference" name="reference">
                                    <x-input name="reference" id="reference" placeholder="Receipt number" />
                                </x-field>
                            </div>
                            <x-button type="submit" size="sm">Record payment</x-button>
                        </form>
                    @endif
                @endcan

                @can('manage-customers')
                    <form method="POST" action="{{ route('customers.opening-due.store', $customer) }}"
                        class="space-y-3 border-t border-slate-200 bg-slate-50/60 p-5">
                        @csrf
                        <p class="text-sm font-medium text-slate-700">
                            {{ (float) $customer->opening_due > 0 ? 'Correct the carried-over balance' : 'Add a balance carried over from before' }}
                        </p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-field label="Total owed then" name="amount" hint="The whole amount, not what is left.">
                                <x-input type="number" step="0.01" min="0" name="amount" id="opening_amount"
                                    value="{{ old('amount', (float) $customer->opening_due ?: null) }}" class="text-right" required />
                            </x-field>
                            <x-field label="Owed as at" name="opening_due_on">
                                <x-input type="date" name="opening_due_on" id="opening_due_on"
                                    value="{{ old('opening_due_on', $customer->opening_due_on?->toDateString() ?? now()->toDateString()) }}" />
                            </x-field>
                            <x-field label="Note" name="opening_due_note">
                                <x-input name="opening_due_note" id="opening_due_note"
                                    value="{{ old('opening_due_note', $customer->opening_due_note) }}" placeholder="e.g. From the old ledger" />
                            </x-field>
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Save balance</x-button>
                        <p class="form-hint">Payments already taken against this balance are kept — only what is left to pay moves.</p>
                    </form>
                @endcan
            </x-card>

            @can('manage-loyalty')
                <x-card title="Adjust points" description="Goodwill credits and corrections. Kept on the history with your name.">
                    <form method="POST" action="{{ route('customers.adjust-points', $customer) }}" class="space-y-3 p-5">
                        @csrf
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-field label="Points" name="points" hint="Negative to take away.">
                                <x-input type="number" step="1" name="points" id="points" value="{{ old('points') }}" required placeholder="e.g. 100" />
                            </x-field>
                            <x-field class="sm:col-span-2" label="Reason" name="reason">
                                <x-input name="reason" id="reason" value="{{ old('reason') }}" required placeholder="e.g. Sorry for the damaged eggs" />
                            </x-field>
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Post adjustment</x-button>
                    </form>
                </x-card>
            @endcan

            <x-card title="Recent bills">
                @if ($recentSales->isEmpty())
                    <p class="px-5 py-4 text-sm text-slate-500">No bills yet. Scan the card, or type the mobile number, at the till.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($recentSales as $sale)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                <div>
                                    <a href="{{ route('sales.show', $sale) }}" class="font-mono font-medium text-slate-800 hover:text-brand-700">{{ $sale->invoice_no }}</a>
                                    <span class="block text-xs text-slate-400">
                                        {{ $sale->sold_at->format('d M Y') }}
                                        @if ($sale->loyalty_points_earned) · +{{ $sale->loyalty_points_earned }} pts @endif
                                        @if ($sale->loyalty_points_redeemed) · −{{ $sale->loyalty_points_redeemed }} pts @endif
                                    </span>
                                </div>
                                <div class="text-right">
                                    <span class="font-semibold text-slate-900">@money($sale->grand_total)</span>
                                    @if ($sale->isVoided())
                                        <x-badge color="red" class="ml-1">Voided</x-badge>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Details">
                <dl class="divide-y divide-slate-100 text-sm">
                    @php
                        $details = [
                            'Mobile' => $customer->formattedPhone(),
                            'Email' => $customer->email ?? '—',
                            'Birthday' => $customer->birth_date?->format('d M') ?? '—',
                            'City' => $customer->city ?? '—',
                            'Address' => $customer->address ?? '—',
                            'Offers' => $customer->marketing_opt_in ? 'Happy to receive' : 'Do not contact',
                            'Enrolled by' => $customer->enroller?->name ?? '—',
                        ];
                    @endphp
                    @foreach ($details as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-2.5">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($customer->notes)
                    <p class="border-t border-slate-200 px-5 py-3 text-sm text-slate-600">{{ $customer->notes }}</p>
                @endif
            </x-card>

            @if ($customer->cards->count() > 1)
                <x-card title="Card history">
                    <ul class="divide-y divide-slate-100">
                        @foreach ($customer->cards as $pastCard)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                <div>
                                    <span class="font-mono text-slate-800">{{ $pastCard->formattedNumber() }}</span>
                                    <span class="block text-xs text-slate-400">
                                        Issued {{ $pastCard->issued_at->format('d M Y') }}
                                        @if ($pastCard->retired_at) · retired {{ $pastCard->retired_at->format('d M Y') }} @endif
                                        @if ($pastCard->retired_reason) · {{ $pastCard->retired_reason }} @endif
                                    </span>
                                </div>
                                <x-badge :color="$pastCard->status->color()">{{ $pastCard->status->label() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @can('delete-records')
                <form method="POST" action="{{ route('customers.destroy', $customer) }}"
                    onsubmit="return confirm('Delete {{ addslashes($customer->name) }} and their points history?')">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="ghost" size="sm" class="text-red-600 hover:bg-red-50">Delete member</x-button>
                    <span class="text-xs text-slate-400">Only possible before their first bill.</span>
                </form>
            @endcan
        </div>
    </div>
</x-layouts.app>
