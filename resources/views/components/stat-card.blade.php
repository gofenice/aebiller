@props(['label', 'value', 'sub' => null, 'tone' => 'slate', 'href' => null, 'icon' => null])

@php
    $tones = [
        'slate' => 'text-slate-900',
        'green' => 'text-emerald-600',
        'amber' => 'text-amber-600',
        'red' => 'text-red-600',
        'blue' => 'text-blue-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    class="card p-4 transition {{ $href ? 'hover:border-brand-300 hover:shadow-sm' : '' }}">
    <div class="flex items-start justify-between gap-2">
        <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ $label }}</p>
        @if ($icon)
            <span class="text-base leading-none">{{ $icon }}</span>
        @endif
    </div>
    <p class="mt-2 text-2xl font-semibold {{ $tones[$tone] ?? $tones['slate'] }}">{{ $value }}</p>
    @if ($sub)
        <p class="mt-1 text-xs text-slate-500">{{ $sub }}</p>
    @endif
</{{ $tag }}>
