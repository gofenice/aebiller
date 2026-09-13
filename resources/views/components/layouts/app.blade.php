@props(['title' => null])

@php
    $user = auth()->user();
    $lowStockCount = \App\Models\Product::active()->lowStock()->count()
        + \App\Models\Product::active()->outOfStock()->count();

    // Only the owner is shown the subscription warning; cashiers cannot act on it.
    $store = \App\Support\StoreContext::get();
    $subscriptionInvoice = $user->isSuperAdmin() && $store !== null
        ? $store->invoices()->unpaid()->orderBy('due_on')->first()
        : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="min-h-full" x-data="{ sidebarOpen: false }">

    {{-- Mobile backdrop --}}
    <div x-show="sidebarOpen" x-cloak x-on:click="sidebarOpen = false"
        class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside x-cloak
        class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
        x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

        <div class="flex h-16 items-center gap-2.5 border-b border-slate-200 px-5">
            <x-logo />
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">{{ config('app.name') }}</p>
                <p class="text-[11px] text-slate-500">Inventory Management</p>
            </div>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            <div class="space-y-1">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="◧">Dashboard</x-nav-link>
            </div>

            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Till</p>
                <x-nav-link :href="route('billing.create')" :active="request()->routeIs('billing.*')" icon="▮">Billing</x-nav-link>
                <x-nav-link :href="route('sales.index')" :active="request()->routeIs('sales.*')" icon="⎘">Bills</x-nav-link>
            </div>

            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Customers</p>
                <x-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')" icon="★">Loyalty Members</x-nav-link>
            </div>

            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Inventory</p>
                <x-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')" icon="▤">Products</x-nav-link>
                <x-nav-link :href="route('stock-entries.index')" :active="request()->routeIs('stock-entries.*')" icon="↓">Stock Entry</x-nav-link>
                <x-nav-link :href="route('stock-adjustments.index')" :active="request()->routeIs('stock-adjustments.*')" icon="⇅">Adjustments</x-nav-link>
                <x-nav-link :href="route('stock-movements.index')" :active="request()->routeIs('stock-movements.*')" icon="≡">Stock Ledger</x-nav-link>
            </div>

            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Money Out</p>
                <x-nav-link :href="route('expenses.index')" :active="request()->routeIs('expenses.*')" icon="¤">Expenses</x-nav-link>
                <x-nav-link :href="route('expense-categories.index')" :active="request()->routeIs('expense-categories.*')" icon="⌗">Expense Categories</x-nav-link>
            </div>

            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Master Data</p>
                <x-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')" icon="⌗">Categories</x-nav-link>
                <x-nav-link :href="route('brands.index')" :active="request()->routeIs('brands.*')" icon="◈">Brands</x-nav-link>
                <x-nav-link :href="route('units.index')" :active="request()->routeIs('units.*')" icon="⚖">Units</x-nav-link>
                <x-nav-link :href="route('suppliers.index')" :active="request()->routeIs('suppliers.*')" icon="◎">Suppliers</x-nav-link>
            </div>

            <div class="space-y-1">
                <p class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Reports</p>
                <x-nav-link :href="route('reports.low-stock')" :active="request()->routeIs('reports.low-stock')" icon="⚠"
                    :badge="$lowStockCount ?: null">Low Stock</x-nav-link>
                <x-nav-link :href="route('reports.expiry')" :active="request()->routeIs('reports.expiry')" icon="⏱">Expiry</x-nav-link>
                <x-nav-link :href="route('reports.valuation')" :active="request()->routeIs('reports.valuation')" icon="¤">Stock Valuation</x-nav-link>
                <x-nav-link :href="route('reports.loyalty')" :active="request()->routeIs('reports.loyalty')" icon="✦">Loyalty</x-nav-link>
                @can('manage-expenses')
                    <x-nav-link :href="route('reports.profit-loss')" :active="request()->routeIs('reports.profit-loss')" icon="↗">Income &amp; Expenses</x-nav-link>
                @endcan
            </div>

            @can('manage-users')
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Administration</p>
                    <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')" icon="◍">Staff Accounts</x-nav-link>
                    @can('manage-loyalty')
                        <x-nav-link :href="route('loyalty.settings')" :active="request()->routeIs('loyalty.*')" icon="★">Loyalty Settings</x-nav-link>
                    @endcan
                </div>
            @endcan
        </nav>

        <div class="border-t border-slate-200 p-3">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-700">
                    {{ str($user->name)->substr(0, 2)->upper() }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-800">{{ $user->name }}</p>
                    <p class="text-[11px] text-slate-500">{{ $user->role->label() }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sign out"
                        class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-red-600">⏻</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" x-on:click="sidebarOpen = true"
                class="rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden">☰</button>

            <form action="{{ route('products.index') }}" method="GET" class="hidden max-w-md flex-1 sm:block">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">⌕</span>
                    <input type="search" name="search" value="{{ request('search') }}"
                        placeholder="Search products by name, SKU or barcode…"
                        class="form-input pl-8">
                </div>
            </form>

            <div class="ml-auto flex items-center gap-2">
                <x-button :href="route('stock-entries.create')" variant="secondary" size="sm">↓ Stock In</x-button>
                <x-button :href="route('billing.create')" size="sm">▮ New Sale</x-button>
            </div>
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8">
            <x-alerts />

            @if ($store?->isOnTrial() && $user->isSuperAdmin())
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-ink-200 bg-ink-50 px-4 py-3 text-sm">
                    <div>
                        <p class="font-semibold text-ink-900">
                            Free trial — {{ $store->trialDaysLeft() }} day(s) left
                        </p>
                        <p class="mt-0.5 text-ink-600">
                            Everything is yours to try until {{ $store->trial_ends_on->format('d M Y') }}. Subscribe before then and nothing is interrupted.
                        </p>
                    </div>
                    <x-button :href="route('subscription.show')" size="sm">Subscribe</x-button>
                </div>
            @endif

            @if ($subscriptionInvoice)
                @php $dueSoon = $subscriptionInvoice->isDueSoon(); @endphp
                <div @class([
                    'mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm',
                    'border-amber-200 bg-amber-50 text-amber-800' => $dueSoon,
                    'border-red-200 bg-red-50 text-red-800' => ! $dueSoon,
                ])>
                    <div>
                        <p class="font-semibold">{{ $dueSoon ? 'Subscription due soon' : 'Subscription overdue' }}</p>
                        <p class="mt-0.5">
                            Invoice {{ $subscriptionInvoice->number }} ({{ $subscriptionInvoice->periodLabel() }}) —
                            {{ $subscriptionInvoice->currency_code }} {{ number_format($subscriptionInvoice->outstanding(), 2) }} —
                            {{ $dueSoon ? 'is due on' : 'was due on' }} {{ $subscriptionInvoice->due_on->format('d M Y') }}.
                            @if (! $dueSoon && $store?->auto_suspend)
                                The shop closes on {{ $subscriptionInvoice->suspendOn()->format('d M Y') }} if it is not paid.
                            @endif
                        </p>
                    </div>
                    <x-button :href="route('subscription.show')" size="sm" :variant="$dueSoon ? 'secondary' : 'danger'">Pay now</x-button>
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
