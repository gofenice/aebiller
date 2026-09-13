@php
    $current = \App\Support\DisplayCurrency::current();
@endphp

{{-- No route behind this: each option is the current page with ?currency= on
     it, which the middleware reads and remembers. Works the same on the bare
     domain and on app. --}}
<select aria-label="Currency" x-on:change="window.location.href = $event.target.value"
    {{ $attributes->merge(['class' => 'cursor-pointer rounded-lg border border-white/20 bg-transparent py-2 pr-7 pl-3 text-sm font-medium text-ink-200 transition hover:border-white/50 hover:text-white focus:ring-2 focus:ring-ae-500 focus:outline-none']) }}>
    @foreach (\App\Support\DisplayCurrency::offered() as $code)
        <option class="bg-ink-950 text-white" value="{{ request()->fullUrlWithQuery(['currency' => $code]) }}" @selected($code === $current)>
            {{ $code }}
        </option>
    @endforeach
</select>
