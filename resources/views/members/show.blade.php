<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $settings->program_name }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-loyalty.card-styles />
</head>
<body class="h-full bg-slate-100">
<div class="mx-auto max-w-md px-4 py-8">
    <div class="mb-6 flex flex-col items-center text-center">
        <x-logo size="lg" class="mb-3" />
        <h1 class="text-lg font-semibold text-slate-900">{{ $settings->program_name }}</h1>
        <p class="text-sm text-slate-500">Hello {{ $customer->firstName() }} — here are your points.</p>
    </div>

    @if ($customer->activeCard)
        <div class="mb-5 flex justify-center">
            <x-loyalty.card-front :customer="$customer" :settings="$settings" />
        </div>
    @endif

    @unless ($customer->is_active)
        <p class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Your membership is on hold. Please speak to the store.
        </p>
    @endunless

    <div class="card overflow-hidden">
        <div class="px-5 py-5 text-center">
            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Your points</p>
            <p class="mt-1 text-4xl font-semibold tracking-tight text-slate-900">{{ number_format($customer->points_balance) }}</p>
            <p class="mt-1 text-sm text-brand-700">Worth @money($customer->pointsValue($settings)) off your shopping</p>
            @if ($customer->points_balance < $settings->min_redeem_points)
                <p class="mt-2 text-xs text-slate-500">
                    Collect {{ number_format($settings->min_redeem_points - max($customer->points_balance, 0)) }} more to start redeeming.
                </p>
            @endif
        </div>

        @if ($summary['expiring_points'] > 0)
            <p class="border-t border-amber-100 bg-amber-50 px-5 py-2.5 text-center text-sm text-amber-800">
                {{ number_format($summary['expiring_points']) }} points expire on {{ $summary['next_expiry']?->format('d M Y') }} — use them before then.
            </p>
        @endif

        @if ($customer->tier)
            <div class="border-t border-slate-200 px-5 py-4 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Your tier</span>
                    <x-badge :color="$customer->tier->card_theme->badgeColor()">{{ $customer->tier->name }}</x-badge>
                </div>
                @if ($customer->tier->perks)
                    <p class="mt-1 text-xs text-slate-500">{{ $customer->tier->perks }}</p>
                @endif
                @if ($summary['next_tier'])
                    <p class="mt-2 text-xs text-slate-600">
                        Spend @money($summary['next_tier']['remaining']) more within {{ $settings->tier_window_months }} months to reach
                        <strong>{{ $summary['next_tier']['tier']->name }}</strong>.
                    </p>
                @endif
            </div>
        @endif
    </div>

    @if ($barcodeSvg)
        <div class="card mt-5 px-6 py-5 text-center">
            <p class="mb-3 text-xs font-medium tracking-wide text-slate-500 uppercase">Forgot your card? Show this at the till</p>
            <div class="mx-auto max-w-64 [&>svg]:h-auto [&>svg]:w-full">{!! $barcodeSvg !!}</div>
        </div>
    @endif

    @if ($transactions->isNotEmpty())
        <div class="card mt-5">
            <h2 class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">Recent activity</h2>
            <ul class="divide-y divide-slate-100">
                @foreach ($transactions as $transaction)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                        <div class="min-w-0">
                            <span class="block text-slate-800">{{ $transaction->type->label() }}</span>
                            <span class="block text-xs text-slate-400">{{ $transaction->created_at->format('d M Y') }}</span>
                        </div>
                        <span @class(['font-semibold', 'text-emerald-600' => $transaction->isCredit(), 'text-slate-600' => ! $transaction->isCredit()])>
                            {{ $transaction->isCredit() ? '+' : '−' }}{{ number_format(abs($transaction->points)) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="mt-6 text-center text-xs text-slate-400">{{ config('app.name') }} · شكراً لتسوقكم معنا</p>
</div>
</body>
</html>
