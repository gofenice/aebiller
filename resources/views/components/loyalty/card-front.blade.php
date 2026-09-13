@props(['customer', 'settings'])

@php
    $theme = $customer->tier?->card_theme ?? \App\Enums\CardTheme::Emerald;
    $palette = $theme->palette();
    $initials = str(config('app.name'))
        ->explode(' ')
        ->take(3)
        ->map(fn (string $word): string => str($word)->substr(0, 1)->upper()->toString())
        ->implode('');
@endphp

<div {{ $attributes->merge(['class' => 'lc lc-front']) }}
    style="--lc-bg: {{ $palette['background'] }}; --lc-fg: {{ $palette['foreground'] }}; --lc-muted: {{ $palette['muted'] }}; --lc-accent: {{ $palette['accent'] }}; --lc-logo: {{ $palette['logo'] }};">
    <span class="lc-ring"></span>

    <div class="lc-top">
        <div class="lc-brand">
            <span class="lc-logo">{{ $initials }}</span>
            <span style="min-width: 0">
                <span class="lc-store">{{ config('app.name') }}</span>
                <span class="lc-program">{{ $settings->program_name }} · بطاقة الولاء</span>
            </span>
        </div>
        <span class="lc-tier">{{ $customer->tier?->name ?? 'Member' }}</span>
    </div>

    <div class="lc-number">{{ $customer->activeCard?->formattedNumber() }}</div>

    <div class="lc-bottom">
        <div style="min-width: 0">
            <span class="lc-label">Member</span>
            <span class="lc-name">{{ $customer->name }}</span>
        </div>
        <div class="lc-since">
            <span class="lc-label">Since</span>
            <span class="lc-value">{{ ($customer->created_at ?? now())->format('m/y') }}</span>
        </div>
    </div>
</div>
