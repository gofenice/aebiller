<x-layouts.plain :title="$locked ? 'Payment required' : 'Subscription'">
    @php
        $owed = $invoices->sum(fn ($invoice) => $invoice->outstanding());
        $currency = $invoices->first()?->currency_code ?? $store->billingCurrency();
    @endphp

    <div class="mb-6 flex flex-col items-center text-center">
        <x-logo size="lg" class="mb-3" />
        <h1 class="text-lg font-semibold text-slate-900">{{ $store->name }}</h1>
        <p class="text-sm text-slate-500">{{ config('tenancy.platform_name') }} subscription</p>
    </div>

    <x-alerts />

    @if ($locked)
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">The shop is locked until this is paid</p>
            <p class="mt-0.5">Tills, stock and reports are closed to everyone. Settling the amount below reopens them at once.</p>
            @if ($store->suspension_reason)
                <p class="mt-2 rounded bg-white/60 px-2 py-1 text-xs">{{ $store->suspension_reason }}</p>
            @endif
        </div>
    @elseif ($invoices->isNotEmpty())
        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-semibold">Payment due soon</p>
            <p class="mt-0.5">Paying now keeps everything running — nothing is locked yet.</p>
        </div>
    @endif

    @if ($locked && $invoices->isEmpty())
        {{-- Closed by hand rather than for money owed. --}}
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-4 text-center">
            <p class="text-sm font-semibold text-slate-900">This shop has been closed by {{ config('tenancy.platform_name') }}</p>
            <p class="mt-1 text-sm text-slate-600">
                {{ $store->suspension_reason ?: 'There is nothing to pay online.' }}
                Please contact {{ config('tenancy.platform_name') }} to have it reopened.
            </p>
        </div>
    @elseif ($invoices->isEmpty())
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-4 text-center">
            <p class="text-sm font-semibold text-emerald-900">Nothing to pay</p>
            <p class="mt-1 text-sm text-emerald-800">
                @if ($store->next_invoice_on)
                    The next payment of {{ $store->feeLabel() }} is due on {{ $store->next_invoice_on->format('d M Y') }}.
                @else
                    This shop has no further payments due.
                @endif
            </p>
        </div>
    @else
        <div class="card divide-y divide-slate-100">
            <div class="px-5 py-4 text-center">
                <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Amount owed</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight text-slate-900">{{ $currency }} {{ number_format($owed, 2) }}</p>
            </div>

            @foreach ($invoices as $invoice)
                <div class="px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-sm font-medium text-slate-800">{{ $invoice->number }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $invoice->periodLabel() }}@if ($invoice->is_prorated) <span class="text-slate-400">(part period)</span>@endif
                                · due {{ $invoice->due_on->format('d M Y') }}
                                @if ($invoice->isDueSoon())
                                    <span class="text-amber-700">(in {{ (int) today()->diffInDays($invoice->due_on) }} day(s))</span>
                                @else
                                    <span class="text-red-600">(overdue)</span>
                                @endif
                            </p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold text-slate-900">
                            {{ $invoice->currency_code }} {{ number_format($invoice->outstanding(), 2) }}
                        </p>
                    </div>

                    @can('manage-subscription')
                        @if ($canPay)
                            <form method="POST" action="{{ route('subscription.pay', $invoice) }}" class="mt-3">
                                @csrf
                                <x-button type="submit" class="w-full">Pay {{ $invoice->currency_code }} {{ number_format($invoice->outstanding(), 2) }} now</x-button>
                            </form>
                            <p class="form-hint text-center">You will be taken to Razorpay to pay by card, UPI or netbanking.</p>
                        @else
                            <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                Online payment is not switched on. Pay by bank transfer and {{ config('tenancy.platform_name') }} will
                                mark it received.
                            </p>
                        @endif
                    @else
                        <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Only the shop owner's account can pay this. Please ask them to sign in.
                        </p>
                    @endcan
                </div>
            @endforeach
        </div>
    @endif

    @can('manage-subscription')
        @if ($canAutoCharge)
            <div class="card mt-5 px-5 py-4">
                @if ($store->autoChargeIsRunning())
                    <p class="text-sm font-semibold text-slate-900">Automatic payment is on</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Your card is charged {{ $store->feeLabel() }} automatically, so nothing is ever locked for a missed date.
                    </p>
                    <form method="POST" action="{{ route('subscription.auto-charge.disable') }}" class="mt-3"
                        onsubmit="return confirm('Turn off automatic payment? You will have to pay each invoice by hand.')">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="secondary" size="sm">Turn off automatic payment</x-button>
                    </form>
                @elseif ($store->autoChargeIsPending())
                    <p class="text-sm font-semibold text-amber-700">Automatic payment is waiting to be authorised</p>
                    <p class="mt-1 text-xs text-slate-500">Finish approving the card with Razorpay, or start again below.</p>
                    <form method="POST" action="{{ route('subscription.auto-charge.enable') }}" class="mt-3">
                        @csrf
                        <x-button type="submit" variant="secondary" size="sm">Continue setting it up</x-button>
                    </form>
                @else
                    <p class="text-sm font-semibold text-slate-900">Pay automatically each {{ strtolower($store->billingPeriod()->label()) === 'yearly' ? 'year' : 'month' }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Leave a card with Razorpay and {{ $store->feeLabel() }} is taken automatically — no invoices to remember,
                        and the shop is never locked for a missed date. You can stop it whenever you like.
                    </p>
                    <form method="POST" action="{{ route('subscription.auto-charge.enable') }}" class="mt-3">
                        @csrf
                        <x-button type="submit" variant="secondary" size="sm">Set up automatic payment</x-button>
                    </form>
                @endif
            </div>
        @endif
    @endcan

    <dl class="mt-5 space-y-1 text-xs text-slate-500">
        <div class="flex justify-between"><dt>Plan</dt><dd>{{ $store->plan?->name ?? 'No plan' }} · {{ $store->feeLabel() }}</dd></div>
        <div class="flex justify-between"><dt>Billing</dt><dd>{{ $store->billingPeriod()->label() }}</dd></div>
        @if ($recentlyPaid->isNotEmpty())
            <div class="flex justify-between">
                <dt>Last paid</dt>
                <dd>{{ $recentlyPaid->first()->paid_at?->format('d M Y') ?? '—' }}</dd>
            </div>
        @endif
    </dl>

    <div class="mt-6 flex items-center justify-between border-t border-slate-200 pt-4">
        @unless ($locked)
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-brand-700 hover:underline">&larr; Back to the shop</a>
        @else
            <span class="text-xs text-slate-400">Questions? Contact {{ config('tenancy.platform_name') }}.</span>
        @endunless

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-slate-500 hover:text-red-600">Sign out</button>
        </form>
    </div>
</x-layouts.plain>
