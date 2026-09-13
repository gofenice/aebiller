<x-layouts.marketing>
    @php
        $registerUrl = route('register');
        $highlight = $monthlyPlans->skip(1)->first() ?? $monthlyPlans->first();
    @endphp

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-ink-950 text-white">
        <div class="ae-slashes absolute inset-0"></div>
        {{-- The logo's angle, blown up as a wash of colour. --}}
        <div class="pointer-events-none absolute -top-40 -right-40 h-[34rem] w-[34rem] -skew-x-12 bg-ae-600/20 blur-3xl"></div>

        <div class="relative mx-auto grid max-w-6xl gap-14 px-4 py-20 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:py-28">
            {{-- min-w-0: without it a grid column is sized by its widest content
                 and drags the whole page sideways on a phone. --}}
            <div class="min-w-0">
                <span class="inline-flex items-center gap-2 rounded-full border border-ae-600/40 bg-ae-600/10 px-3 py-1 text-xs font-semibold tracking-wide text-ae-300 uppercase">
                    {{ $trialDays }} days free · no card
                </span>

                <h1 class="mt-6 text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                    The till, the stock and the
                    <span class="relative inline-block">
                        loyalty card
                        <span class="absolute right-0 -bottom-1 left-0 h-1.5 -skew-x-[25deg] bg-ae-600"></span>
                    </span>
                    — one shop, one screen.
                </h1>

                <p class="mt-6 max-w-xl text-lg leading-relaxed text-ink-200">
                    Scan, bill and print a proper VAT receipt in seconds. Keep stock, batches and expiry honest.
                    Give regulars a card worth carrying. Your shop gets its own address and is ready the minute you sign up.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-3">
                    <a href="{{ $registerUrl }}"
                        class="rounded-lg bg-ae-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-ae-950/40 transition hover:bg-ae-500">
                        Start your {{ $trialDays }}-day trial
                    </a>
                    <a href="#pricing"
                        class="rounded-lg border border-white/20 px-6 py-3.5 text-sm font-semibold text-white transition hover:border-white/50 hover:bg-white/5">
                        See subscription plans
                    </a>
                </div>

                <dl class="mt-12 grid max-w-lg grid-cols-2 gap-6 border-t border-white/10 pt-8 sm:grid-cols-3">
                    @foreach ([
                        ['Set up in', 'minutes', 'not weeks'],
                        ['Works on', 'any screen', 'phone, tablet, till'],
                        ['Receipts', 'VAT-ready', 'Arabic & English'],
                    ] as [$label, $value, $sub])
                        <div>
                            <dt class="text-[11px] font-semibold tracking-[0.18em] text-ink-500 uppercase">{{ $label }}</dt>
                            <dd class="mt-1.5 text-lg font-semibold text-white">{{ $value }}</dd>
                            <dd class="text-xs text-ink-400">{{ $sub }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- A sketch of the actual till, not a stock photo --}}
            <div class="relative min-w-0 lg:pl-6">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-3 shadow-2xl backdrop-blur">
                    <div class="rounded-xl bg-white p-5 text-ink-900 shadow-xl">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                            <div>
                                <p class="text-[11px] font-semibold tracking-[0.18em] text-slate-400 uppercase">Bill</p>
                                <p class="font-mono text-sm font-semibold">INV-2609-0184</p>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 ring-inset">
                                Card · paid
                            </span>
                        </div>

                        <table class="mt-3 w-full text-xs">
                            <tbody class="divide-y divide-slate-100">
                                @foreach ([
                                    ['Basmati rice 5 kg', '1', '46.00'],
                                    ['Tomatoes (loose)', '1.240 kg', '7.43'],
                                    ['Fresh milk 1 L', '4', '23.00'],
                                    ['Arabic coffee 250 g', '2', '38.00'],
                                ] as [$item, $qty, $amount])
                                    <tr>
                                        <td class="py-2 pr-2 text-slate-700">{{ $item }}</td>
                                        <td class="py-2 text-right whitespace-nowrap text-slate-400">{{ $qty }}</td>
                                        <td class="py-2 pl-2 text-right font-medium">{{ $amount }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-3 space-y-1 border-t border-dashed border-slate-300 pt-3 text-xs">
                            <div class="flex justify-between text-slate-500"><span>Subtotal excl. VAT</span><span>99.51</span></div>
                            <div class="flex justify-between text-slate-500"><span>VAT 15%</span><span>14.92</span></div>
                            <div class="flex justify-between text-ae-700"><span>Points redeemed (500)</span><span>− 5.00</span></div>
                            <div class="flex items-baseline justify-between border-t border-slate-300 pt-2 text-base font-semibold">
                                <span>Total</span><span>SAR 109.43</span>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between rounded-lg bg-ink-950 px-3 py-2.5 text-white">
                            <div>
                                <p class="text-[10px] tracking-[0.18em] text-ink-400 uppercase">Member</p>
                                <p class="text-sm font-semibold">Aisha Rahman · Gold</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] tracking-[0.18em] text-ink-400 uppercase">Points</p>
                                <p class="text-sm font-semibold text-ae-400">+109</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="absolute -bottom-6 -left-4 hidden rounded-xl border border-white/10 bg-ink-900 px-4 py-3 shadow-xl lg:block">
                    <p class="text-[10px] tracking-[0.18em] text-ink-500 uppercase">Low stock</p>
                    <p class="mt-0.5 text-sm font-semibold text-white">7 items need reordering</p>
                </div>
            </div>
        </div>
    </section>

    {{-- What it does --}}
    <section id="what-it-does" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-20 sm:px-6 lg:py-28">
        <div class="max-w-2xl">
            <span class="ae-rule"></span>
            <h2 class="mt-6 text-3xl font-semibold tracking-tight sm:text-4xl">Everything the counter needs, nothing it doesn't</h2>
            <p class="mt-4 text-lg text-ink-600">
                Built around how a grocery shop actually runs: weighed produce, packet goods, VAT, expiry dates and regulars
                who expect to be recognised.
            </p>
        </div>

        <div class="mt-14 grid gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Till that keeps up', 'Scan the barcode or type the code. Weighed items take the scale reading, cash tendering works out the change, and the receipt prints with the QR that opens the bill online.'],
                ['Stock you can trust', 'Goods receipts, batches, expiry dates and a full ledger of every movement. The dashboard says what is running low and what is going off before a customer finds it.'],
                ['Loyalty worth carrying', 'Points on every bill, tiers that move with spend, and a printable card with a barcode the till scans. Members can check their own points from the QR on the back.'],
                ['VAT done properly', 'Prices are kept VAT-inclusive the way shops quote them, and the tax is split out on the receipt — bilingual, and ready for a simplified tax invoice.'],
                ['Money out as well as in', 'Rent, salaries, electricity, supplier bills — recorded against categories, so the income and expenses report shows what the shop actually made.'],
                ['Shelf labels and barcodes', 'Generate EAN-13 barcodes for loose and own-packed goods, then print a sheet of shelf labels that scanners read the first time.'],
            ] as [$heading, $copy])
                <div>
                    <h3 class="flex items-baseline gap-3 text-lg font-semibold">
                        <span class="h-3 w-3 shrink-0 -skew-x-[25deg] bg-ae-600"></span>
                        {{ $heading }}
                    </h3>
                    <p class="mt-3 text-[15px] leading-relaxed text-ink-600">{{ $copy }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section class="border-y border-ink-100 bg-ink-50">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
                <div>
                    <span class="ae-label">Getting started</span>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight">Open by this afternoon</h2>
                    <p class="mt-4 text-ink-600">
                        No installation, no server to buy, no visit from anyone. Sign up and the shop is there, waiting.
                    </p>
                </div>

                <ol class="grid gap-6 sm:grid-cols-3">
                    @foreach ([
                        ['01', 'Sign up', 'Shop name, your name, a password. Pick the address you want.'],
                        ['02', 'Get your address', 'yourshop.'.config('tenancy.central_domain').' is yours, with units, categories and loyalty tiers already set.'],
                        ['03', 'Start billing', 'Add products, scan, take payment. Print the first receipt within minutes.'],
                    ] as [$step, $heading, $copy])
                        <li class="border-t-2 border-ink-900 pt-5">
                            <span class="font-mono text-xs font-semibold text-ae-600">{{ $step }}</span>
                            <h3 class="mt-2 font-semibold">{{ $heading }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-ink-600">{{ $copy }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="pricing" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-20 sm:px-6 lg:py-28">
        <div class="max-w-2xl">
            <span class="ae-rule"></span>
            <h2 class="mt-6 text-3xl font-semibold tracking-tight sm:text-4xl">One price, the whole shop</h2>
            <p class="mt-4 text-lg text-ink-600">
                Every plan includes the till, stock, loyalty, reports and unlimited staff accounts. Start with {{ $trialDays }} days
                free — nothing to pay until the trial ends.
            </p>
        </div>

        @if ($monthlyPlans->isEmpty() && $otherPlans->isEmpty())
            <p class="mt-10 rounded-xl border border-dashed border-ink-300 px-6 py-10 text-center text-ink-500">
                Prices are being updated. Please check back shortly.
            </p>
        @else
            <div class="mt-12 grid gap-6 lg:grid-cols-3">
                @foreach ($monthlyPlans as $plan)
                    @php $featured = $highlight !== null && $plan->is($highlight); @endphp
                    <div @class([
                        'relative flex flex-col rounded-2xl border p-7',
                        'border-ink-900 bg-ink-950 text-white shadow-xl' => $featured,
                        'border-ink-200 bg-white' => ! $featured,
                    ])>
                        @if ($featured)
                            <span class="absolute -top-3 left-7 rounded-full bg-ae-600 px-3 py-1 text-[11px] font-semibold tracking-wide text-white uppercase">
                                Most shops choose this
                            </span>
                        @endif

                        <h3 class="text-lg font-semibold">{{ $plan->name }}</h3>
                        <p @class(['mt-1 text-sm', 'text-ink-300' => $featured, 'text-ink-500' => ! $featured])>
                            {{ $plan->description }}
                        </p>

                        @php $price = $plan->amountIn($currency); @endphp
                        <p class="mt-6 flex items-baseline gap-1.5">
                            <span class="text-sm font-medium">{{ trim(\App\Support\DisplayCurrency::symbolOf($price['currency'])) }}</span>
                            <span class="text-4xl font-semibold tracking-tight">{{ number_format($price['amount'], 0) }}</span>
                            <span @class(['text-sm', 'text-ink-400' => $featured, 'text-ink-500' => ! $featured])>
                                {{ $plan->billing_period->suffix() }}
                            </span>
                        </p>

                        <a href="{{ route('register', ['plan' => $plan->slug]) }}"
                            @class([
                                'mt-7 rounded-lg px-4 py-3 text-center text-sm font-semibold transition',
                                'bg-ae-600 text-white hover:bg-ae-500' => $featured,
                                'border border-ink-900 text-ink-900 hover:bg-ink-900 hover:text-white' => ! $featured,
                            ])>
                            Start {{ $trialDays }} days free
                        </a>
                    </div>
                @endforeach
            </div>

            @if ($otherPlans->isNotEmpty())
                <div class="mt-6 grid gap-6 sm:grid-cols-2">
                    @foreach ($otherPlans as $plan)
                        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-ink-200 bg-ink-50 px-7 py-6">
                            <div>
                                <h3 class="font-semibold">
                                    {{ $plan->name }}
                                    <span class="ml-1 rounded bg-ae-600/10 px-2 py-0.5 text-[11px] font-semibold tracking-wide text-ae-700 uppercase">
                                        {{ $plan->billing_period->label() }}
                                    </span>
                                </h3>
                                <p class="mt-1 text-sm text-ink-500">{{ $plan->description }}</p>
                            </div>
                            <div class="flex items-center gap-5">
                                @php $price = $plan->amountIn($currency); @endphp
                                <p class="text-right">
                                    <span class="text-2xl font-semibold tracking-tight">{{ \App\Support\DisplayCurrency::format($price['amount'], $price['currency']) }}</span>
                                    <span class="block text-xs text-ink-500">{{ $plan->billing_period->suffix() }}</span>
                                </p>
                                <a href="{{ route('register', ['plan' => $plan->slug]) }}"
                                    class="rounded-lg border border-ink-900 px-4 py-2.5 text-sm font-semibold transition hover:bg-ink-900 hover:text-white">
                                    Subscribe
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        <p class="mt-8 text-sm text-ink-500">
            Prices shown in {{ \App\Support\DisplayCurrency::nameOf($currency) }} — change the currency at the top of the page.
            Pay by card, UPI or netbanking through Razorpay, or by bank transfer. Cancel whenever you like — your data stays yours.
        </p>
    </section>

    {{-- Questions --}}
    <section id="questions" class="scroll-mt-24 border-t border-ink-100 bg-ink-50">
        <div class="mx-auto max-w-4xl px-4 py-20 sm:px-6">
            <span class="ae-label">Questions</span>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight">Before you sign up</h2>

            <div class="mt-10 divide-y divide-ink-200 border-y border-ink-200">
                @foreach ([
                    ['What happens after the '.$trialDays.' days?', 'Your first invoice falls due the day the trial ends, charged only for the days left in that month. Pay it and nothing changes; leave it and the shop pauses until you do — your data is kept either way.'],
                    ['Do I need a card to start?', 'No. Sign up, use it for '.$trialDays.' days, and decide after that. We only ask for payment when the trial ends.'],
                    ['Can my staff use it at the same time?', 'Yes. Staff accounts are unlimited, and you decide who is an owner and who is a cashier. Only owners can delete records or see the money screens.'],
                    ['Does it work on the till computer I already have?', 'It runs in a browser, so anything from a phone to an old counter PC will do. A USB barcode scanner works as a keyboard — no driver, no setup.'],
                    ['Is my shop separate from other shops?', 'Completely. Your shop has its own address and its own data; nothing is shared or visible to anyone else.'],
                ] as [$question, $answer])
                    <details class="group py-5" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium">
                            {{ $question }}
                            <span class="text-ae-600 transition group-open:rotate-45">＋</span>
                        </summary>
                        <p class="mt-3 max-w-3xl text-[15px] leading-relaxed text-ink-600">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Closing call --}}
    <section class="relative overflow-hidden bg-ink-950 text-white">
        <div class="ae-slashes absolute inset-0"></div>
        <div class="relative mx-auto flex max-w-6xl flex-col items-start justify-between gap-8 px-4 py-16 sm:px-6 md:flex-row md:items-center">
            <div>
                <h2 class="text-3xl font-semibold tracking-tight">Open your shop on {{ config('tenancy.central_domain') }}</h2>
                <p class="mt-3 text-ink-300">{{ $trialDays }} days free. No card. Ready before the next delivery arrives.</p>
            </div>
            <a href="{{ $registerUrl }}"
                class="shrink-0 rounded-lg bg-ae-600 px-7 py-4 text-sm font-semibold text-white shadow-lg shadow-ae-950/40 transition hover:bg-ae-500">
                Create my shop
            </a>
        </div>
    </section>
</x-layouts.marketing>
