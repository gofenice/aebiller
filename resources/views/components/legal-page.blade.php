@props(['title', 'summary' => null, 'updatedOn' => null])

<x-layouts.marketing :title="$title" :description="$summary">
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 sm:py-20">
        <p class="text-xs font-semibold tracking-[0.2em] text-brand-600 uppercase">{{ config('tenancy.platform_name') }}</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-ink-900 sm:text-4xl">{{ $title }}</h1>

        @if ($summary)
            <p class="mt-4 text-lg leading-relaxed text-ink-600">{{ $summary }}</p>
        @endif

        @if ($updatedOn)
            <p class="mt-4 text-sm text-ink-500">Last updated {{ $updatedOn->format('d F Y') }}</p>
        @endif

        {{-- prose-like spacing without the plugin: headings and paragraphs set here. --}}
        <div class="mt-10 space-y-8 text-base leading-relaxed text-ink-700
            [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-ink-900
            [&_h2]:mb-3 [&_h3]:font-semibold [&_h3]:text-ink-900 [&_h3]:mb-2
            [&_p]:mb-3 [&_ul]:mb-3 [&_ul]:space-y-1.5 [&_ul]:pl-5 [&_li]:list-disc
            [&_a]:text-brand-700 [&_a]:underline [&_a:hover]:text-brand-800">
            {{ $slot }}
        </div>

        <div class="mt-14 flex flex-wrap gap-4 border-t border-ink-200 pt-6 text-sm">
            <a href="{{ route('legal.terms') }}" class="text-ink-600 transition hover:text-ink-900">Terms of Service</a>
            <a href="{{ route('legal.privacy') }}" class="text-ink-600 transition hover:text-ink-900">Privacy Policy</a>
            <a href="{{ route('legal.refunds') }}" class="text-ink-600 transition hover:text-ink-900">Refund &amp; Cancellation</a>
            <a href="{{ route('legal.contact') }}" class="text-ink-600 transition hover:text-ink-900">Contact Us</a>
        </div>
    </div>
</x-layouts.marketing>
