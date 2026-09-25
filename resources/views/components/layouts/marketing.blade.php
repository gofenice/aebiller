@props(['title' => null, 'description' => null])

@php
    $platform = config('tenancy.platform_name');
    $appHost = config('tenancy.app_subdomain').'.'.config('tenancy.central_domain');
    $registerUrl = route('register');
    $findUrl = route('customer.find');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · '.$platform : $platform.' — shop billing, stock and loyalty' }}</title>
    <meta name="description" content="{{ $description ?? 'Till, stock, VAT receipts and a loyalty programme for small supermarkets. Your own address, running in minutes.' }}">
    <link rel="icon" href="{{ asset('favicon.png') }}" sizes="any">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- overflow-x-clip: the hero's angled wash sits deliberately outside the page. --}}
<body class="h-full overflow-x-clip bg-white text-ink-900 antialiased">
    <header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-white/10 bg-ink-950/95 backdrop-blur">
        <div class="mx-auto flex h-18 max-w-6xl items-center gap-6 px-4 sm:px-6">
            <a href="{{ route('marketing.home') }}" class="shrink-0">
                <x-ae-logo variant="mark" ink="white" class="h-9" />
                <span class="sr-only">{{ $platform }}</span>
            </a>

            <nav class="hidden items-center gap-7 text-sm font-medium text-ink-200 md:flex">
                <a href="#what-it-does" class="transition hover:text-white">What it does</a>
                <a href="#pricing" class="transition hover:text-white">Pricing</a>
                <a href="#questions" class="transition hover:text-white">Questions</a>
            </nav>

            <div class="ml-auto hidden items-center gap-3 md:flex">
                <x-currency-switcher />
                <a href="{{ $findUrl }}" class="text-sm font-medium text-ink-200 transition hover:text-white">Sign in</a>
                <a href="{{ $registerUrl }}"
                    class="rounded-lg bg-ae-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-ae-500">
                    Start free
                </a>
            </div>

            <button type="button" x-on:click="open = ! open"
                class="ml-auto rounded-md p-2 text-ink-200 transition hover:bg-white/5 hover:text-white md:hidden">☰</button>
        </div>

        <div x-show="open" x-cloak class="border-t border-white/10 bg-ink-950 px-4 py-4 md:hidden">
            <nav class="flex flex-col gap-3 text-sm text-ink-200">
                <a href="#what-it-does" x-on:click="open = false">What it does</a>
                <a href="#pricing" x-on:click="open = false">Pricing</a>
                <a href="#questions" x-on:click="open = false">Questions</a>
                <a href="{{ $findUrl }}">Sign in</a>
                <x-currency-switcher class="mt-1 w-full" />
                <a href="{{ $registerUrl }}" class="mt-1 rounded-lg bg-ae-600 px-4 py-2 text-center font-semibold text-white">Start free</a>
            </nav>
        </div>
    </header>

    {{ $slot }}

    <footer class="border-t border-white/10 bg-ink-950 text-ink-300">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
            <div class="flex flex-col justify-between gap-8 md:flex-row">
                <div class="max-w-xs">
                    <x-ae-logo variant="wordmark" ink="white" class="h-14" />
                    <p class="mt-4 text-sm leading-relaxed">
                        Billing, stock and loyalty for small supermarkets and grocery shops.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-10 text-sm sm:grid-cols-4">
                    <div>
                        <p class="mb-3 text-xs font-semibold tracking-[0.2em] text-ink-500 uppercase">Product</p>
                        <ul class="space-y-2">
                            <li><a href="#what-it-does" class="transition hover:text-white">What it does</a></li>
                            <li><a href="#pricing" class="transition hover:text-white">Pricing</a></li>
                            <li><a href="#questions" class="transition hover:text-white">Questions</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="mb-3 text-xs font-semibold tracking-[0.2em] text-ink-500 uppercase">Your shop</p>
                        <ul class="space-y-2">
                            <li><a href="{{ $registerUrl }}" class="transition hover:text-white">Create a shop</a></li>
                            <li><a href="{{ $findUrl }}" class="transition hover:text-white">Sign in</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="mb-3 text-xs font-semibold tracking-[0.2em] text-ink-500 uppercase">Company</p>
                        <ul class="space-y-2">
                            <li><a href="{{ route('legal.contact') }}" class="transition hover:text-white">Contact us</a></li>
                            <li><span class="text-ink-500">{{ $appHost }}</span></li>
                        </ul>
                    </div>
                    <div>
                        <p class="mb-3 text-xs font-semibold tracking-[0.2em] text-ink-500 uppercase">Legal</p>
                        <ul class="space-y-2">
                            <li><a href="{{ route('legal.terms') }}" class="transition hover:text-white">Terms of Service</a></li>
                            <li><a href="{{ route('legal.privacy') }}" class="transition hover:text-white">Privacy Policy</a></li>
                            <li><a href="{{ route('legal.refunds') }}" class="transition hover:text-white">Refund &amp; Cancellation</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="mt-10 flex flex-col justify-between gap-2 border-t border-white/10 pt-6 text-xs text-ink-500 sm:flex-row">
                <p>© {{ now()->year }} {{ $platform }}. All rights reserved.</p>
                <p>Built for shops that open early.</p>
            </div>
        </div>
    </footer>
</body>
</html>
