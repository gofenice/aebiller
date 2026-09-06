@props(['title', 'description' => null, 'icon' => null])

<section class="card overflow-hidden">
    <header class="border-b border-slate-200 bg-slate-50/70 px-5 py-3.5">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            @if ($icon)
                <span class="text-base leading-none">{{ $icon }}</span>
            @endif
            {{ $title }}
        </h2>
        @if ($description)
            <p class="mt-0.5 text-xs text-slate-500">{{ $description }}</p>
        @endif
    </header>

    <div class="p-5">
        {{ $slot }}
    </div>
</section>
