<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sale->invoice_no }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100">
<div class="mx-auto max-w-md px-4 py-8">
    <div class="mb-5 flex flex-col items-center text-center">
        <x-logo size="lg" class="mb-3" />
        <h1 class="text-lg font-semibold text-slate-900">{{ config('app.name') }}</h1>
        <p class="text-sm text-slate-500">Your bill</p>
    </div>

    <x-bill.receipt :sale="$sale" />

    <p class="mt-5 text-center text-xs text-slate-400">
        Keep this link to view the bill again — {{ $sale->sold_at->format('d M Y') }}
    </p>
</div>
</body>
</html>
