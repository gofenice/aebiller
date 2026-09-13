@props(['customer', 'settings', 'barcodeSvg', 'qrSvg'])

@php
    $theme = $customer->tier?->card_theme ?? \App\Enums\CardTheme::Emerald;
@endphp

<div {{ $attributes->merge(['class' => 'lc lc-back']) }} style="--lc-band: {{ $theme->palette()['band'] }};">
    <div class="lc-band">
        <span>{{ config('app.name') }}</span>
        <span>{{ $settings->program_name }}</span>
    </div>

    <div class="lc-back-body">
        {{-- The barcode is what the till scans; the QR opens the member's balance page. --}}
        <div class="lc-barcode">{!! $barcodeSvg !!}</div>
        <div class="lc-qr">
            {!! $qrSvg !!}
            <span>Scan to see your points</span>
        </div>
    </div>

    <p class="lc-terms">{{ $settings->cardTerms() }}</p>

    <div class="lc-contact">
        <span>{{ config('inventory.store_phone') ?: 'If found, please return it to the store.' }}</span>
        <span>{{ config('inventory.store_address') }}</span>
    </div>
</div>
