@props(['title' => null])

@php
    $admin = auth('platform')->user();
    $platformName = config('tenancy.platform_name');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.png') }}" sizes="any">
    <title>{{ $title ? $title.' · ' : '' }}{{ $platformName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50">
<div class="min-h-full">
    <header class="border-b border-slate-200 bg-slate-900">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('platform.home') }}" class="flex items-center gap-3">
                <x-ae-logo variant="mark" ink="white" class="h-7" />
                <span class="text-sm font-semibold text-white">{{ $platformName }}</span>
            </a>

            <nav class="flex items-center gap-1">
                <a href="{{ route('platform.home') }}"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ request()->routeIs('platform.home') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    Dashboard
                </a>
                <a href="{{ route('platform.stores.index') }}"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ request()->routeIs('platform.stores.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    Stores
                </a>
                <a href="{{ route('platform.billing.index') }}"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ request()->routeIs('platform.billing.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    Billing
                </a>
                <a href="{{ route('platform.plans.index') }}"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ request()->routeIs('platform.plans.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    Plans
                </a>
                <a href="{{ route('platform.settings.edit') }}"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ request()->routeIs('platform.settings.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    Settings
                </a>
            </nav>

            <div class="ml-auto flex items-center gap-3">
                <span class="hidden text-xs text-slate-400 sm:block">{{ $admin?->name }}</span>
                <form method="POST" action="{{ route('platform.logout') }}">
                    @csrf
                    <button type="submit" title="Sign out"
                        class="rounded-md p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-red-400">⏻</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <x-alerts />
        {{ $slot }}
    </main>
</div>
</body>
</html>
