@props(['href', 'active' => false, 'icon' => null, 'badge' => null])

<a href="{{ $href }}"
    class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
        {{ $active ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
    @if ($icon)
        <span class="w-4 shrink-0 text-center text-[13px] leading-none {{ $active ? '' : 'opacity-70 group-hover:opacity-100' }}">{{ $icon }}</span>
    @endif
    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    @if ($badge)
        <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">{{ $badge }}</span>
    @endif
</a>
