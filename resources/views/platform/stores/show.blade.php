<x-layouts.platform :title="$store->name">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('platform.stores.index') }}" class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-brand-700">&larr; All stores</a>
            <h1 class="flex items-center gap-2 text-xl font-semibold tracking-tight text-slate-900">
                {{ $store->name }}
                <x-badge :color="$store->status->color()">{{ $store->status->label() }}</x-badge>
            </h1>
            <p class="mt-1 font-mono text-sm text-slate-500">{{ $store->host() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-button :href="$store->url()" target="_blank" variant="secondary">Open store ↗</x-button>
            <x-button :href="route('platform.stores.edit', $store)">Edit settings</x-button>
        </div>
    </div>

    @if ($newAccount)
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
            <p class="text-sm font-semibold text-emerald-900">Owner's sign-in — shown once</p>
            <p class="mt-1 text-sm text-emerald-800">
                Address: <span class="font-mono">{{ $store->url() }}</span><br>
                Email: <span class="font-mono">{{ $newAccount['email'] }}</span><br>
                Password: <span class="font-mono font-semibold">{{ $newAccount['password'] }}</span>
            </p>
            <p class="mt-2 text-xs text-emerald-700">Hand this to the owner and ask them to change the password after signing in.</p>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Staff accounts" :value="number_format($store->users_count)" icon="◍" />
        <x-stat-card label="Products" :value="number_format($store->products_count)" icon="▤" />
        <x-stat-card label="Loyalty members" :value="number_format($store->customers_count)" icon="★" />
        <x-stat-card label="Bills this month" :value="number_format($store->bills_this_month)" tone="blue" icon="▮" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Settings">
                <dl class="divide-y divide-slate-100 text-sm">
                    @php
                        $rows = [
                            'Address' => $store->host(),
                            'Legal name' => $store->legal_name ?? '—',
                            'Currency' => $store->currency_code.' ('.trim($store->currency_symbol).')',
                            'Time zone' => $store->timezone,
                            'VAT number' => $store->vat_number ?? '—',
                            'Shop phone' => $store->phone ?? '—',
                            'Shop address' => $store->address ?? '—',
                            'Expiry warning' => $store->expiry_alert_days.' days',
                            'Created' => $store->created_at->format('d M Y'),
                        ];
                    @endphp
                    @foreach ($rows as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-2.5">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($store->notes)
                    <p class="border-t border-slate-200 px-5 py-3 text-sm text-slate-600">{{ $store->notes }}</p>
                @endif
            </x-card>

            <x-card title="Subscription" :description="$store->periodFee() > 0
                ? $store->feeLabel()
                : 'No charge set — this store is not invoiced.'">
                <x-slot:actions>
                    <x-button :href="route('platform.billing.index', ['store' => $store->slug])" variant="secondary" size="sm">All invoices</x-button>
                </x-slot:actions>

                <dl class="divide-y divide-slate-100 text-sm">
                    @php
                        $billing = [
                            'Plan' => ($store->plan?->name ?? 'No plan').' · '.$store->billingPeriod()->label(),
                            'Next renewal' => $store->next_invoice_on
                                ? $store->next_invoice_on->format('d M Y').' (invoiced '.$store->invoiceLeadDays().' days earlier)'
                                : ($store->billingPeriod() === \App\Enums\BillingPeriod::Lifetime ? 'Lifetime — paid once' : 'Not scheduled'),
                            'Grace period' => $store->grace_days.' days after the due date',
                            'If unpaid' => $store->auto_suspend ? 'Closes automatically' : 'Warning only',
                            'Automatic payment' => $store->autoChargeIsRunning()
                                ? 'On — card charged by Razorpay'
                                : ($store->autoChargeIsPending() ? 'Waiting for the shop to authorise a card' : 'Off — pays invoice by invoice'),
                            'Outstanding' => $store->billingCurrency().' '.number_format($outstanding, 2),
                        ];
                    @endphp
                    @foreach ($billing as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-2.5">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($invoices->isNotEmpty())
                    <table class="min-w-full border-t border-slate-200">
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($invoices as $invoice)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="table-cell">
                                        <a href="{{ route('platform.billing.show', $invoice) }}" class="font-mono text-xs font-medium text-slate-800 hover:text-brand-700">{{ $invoice->number }}</a>
                                        <span class="block text-xs text-slate-400">{{ $invoice->periodLabel() }}</span>
                                    </td>
                                    <td class="table-cell text-right text-slate-600">{{ $invoice->currency_code }} {{ number_format((float) $invoice->amount, 2) }}</td>
                                    <td class="table-cell text-right"><x-badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            <x-card title="Staff accounts" description="The shop manages these itself; shown here for support.">
                @if ($staff->isEmpty())
                    <x-empty-state icon="◍" title="No staff accounts yet" />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($staff as $member)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                <div>
                                    <span class="font-medium text-slate-800">{{ $member->name }}</span>
                                    <span class="block text-xs text-slate-400">{{ $member->email }}</span>
                                </div>
                                <div class="text-right">
                                    <x-badge :color="$member->isSuperAdmin() ? 'violet' : 'slate'">{{ $member->role->label() }}</x-badge>
                                    <span class="mt-0.5 block text-xs text-slate-400">
                                        {{ $member->last_login_at ? 'Last in '.$member->last_login_at->diffForHumans() : 'Never signed in' }}
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Contact">
                <dl class="divide-y divide-slate-100 text-sm">
                    @foreach (['Owner' => $store->owner_name, 'Email' => $store->owner_email, 'Mobile' => $store->owner_phone] as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-2.5">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800">{{ $value ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            @if ($store->isSuspended())
                <x-card title="Suspended">
                    <div class="space-y-3 p-5">
                        <p class="text-sm text-slate-600">
                            Closed {{ $store->suspended_at?->diffForHumans() }}.
                            @if ($store->suspension_reason)
                                <span class="mt-1 block rounded bg-amber-50 px-2 py-1 text-amber-800">{{ $store->suspension_reason }}</span>
                            @endif
                        </p>
                        <form method="POST" action="{{ route('platform.stores.reactivate', $store) }}">
                            @csrf
                            <x-button type="submit">Reopen the store</x-button>
                        </form>
                    </div>
                </x-card>
            @else
                <x-card title="Suspend this store" description="Used when a month goes unpaid. Nothing is deleted.">
                    <form method="POST" action="{{ route('platform.stores.suspend', $store) }}" class="space-y-3 p-5"
                        onsubmit="return confirm('Suspend {{ addslashes($store->name) }}? Their staff will not be able to use the till.')">
                        @csrf
                        <x-field label="Reason shown to the shop" name="suspension_reason">
                            <x-input name="suspension_reason" id="suspension_reason" value="Monthly payment overdue" />
                        </x-field>
                        <x-button type="submit" variant="danger" size="sm">Suspend store</x-button>
                    </form>
                </x-card>
            @endif

            <x-card title="Archive this store"
                description="The shop stops answering and gives up its address. Its data is kept for {{ \App\Models\Store::KEEP_ARCHIVED_DAYS }} days, so this can be undone.">
                <form method="POST" action="{{ route('platform.stores.destroy', $store) }}" class="space-y-3 p-5"
                    onsubmit="return confirm('Archive {{ addslashes($store->name) }}? Staff lose access at once and {{ $store->slug }} becomes free for another shop.')">
                    @csrf
                    @method('DELETE')
                    <dl class="space-y-1 text-xs text-slate-500">
                        <div class="flex justify-between"><dt>Products</dt><dd class="font-medium text-slate-700">{{ number_format($store->products()->count()) }}</dd></div>
                        <div class="flex justify-between"><dt>Bills</dt><dd class="font-medium text-slate-700">{{ number_format($store->sales()->count()) }}</dd></div>
                        <div class="flex justify-between"><dt>Staff logins</dt><dd class="font-medium text-slate-700">{{ number_format($store->users()->count()) }}</dd></div>
                    </dl>
                    <x-button type="submit" variant="danger" size="sm">Archive store</x-button>
                    <p class="form-hint">
                        After {{ \App\Models\Store::KEEP_ARCHIVED_DAYS }} days an archived store is deleted for good, along with
                        everything above. Delete it sooner from Stores → Archived.
                    </p>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.platform>
