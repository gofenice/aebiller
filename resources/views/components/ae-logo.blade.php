@props([
    'variant' => 'wordmark',
    'ink' => 'white',
    'class' => 'h-9',
])

{{-- The supplied logo files, trimmed and made transparent. --}}
@php
    $file = match (true) {
        $variant === 'mark' && $ink === 'white' => 'ae-mark-white.png',
        $variant === 'mark' => 'ae-mark-ink.png',
        $ink === 'white' => 'ae-wordmark-white.png',
        default => 'ae-wordmark-ink.png',
    };
@endphp

<img src="{{ asset('brand/'.$file) }}" alt="{{ config('tenancy.platform_name') }}"
    {{ $attributes->merge(['class' => 'w-auto '.$class]) }}>
