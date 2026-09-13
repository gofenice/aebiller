<x-layouts.marketing title="Sign in">
    <section class="mx-auto max-w-xl px-4 py-20 sm:px-6">
        <span class="ae-rule"></span>
        <h1 class="mt-6 text-3xl font-semibold tracking-tight">Sign in to your shop</h1>
        <p class="mt-3 text-ink-600">
            Each shop signs in at its own address. Enter yours — or the email you signed up with, and we will find it.
        </p>

        <x-alerts />

        <form method="POST" action="{{ route('customer.find') }}" class="mt-8">
            @csrf
            <label for="search" class="form-label">Shop address or email</label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input type="text" name="search" id="search" required class="form-input" autofocus
                    value="{{ $searched ?? '' }}" placeholder="al-noor  ·  or  you@example.com">
                <button type="submit"
                    class="shrink-0 rounded-lg bg-ink-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-ink-800">
                    Continue
                </button>
            </div>
            @error('search') <p class="mt-1 text-xs font-medium text-ae-700">{{ $message }}</p> @enderror
        </form>

        @isset($searched)
            @if ($matches->isNotEmpty())
                <div class="mt-8">
                    <p class="ae-label">Shops for {{ $searched }}</p>
                    <ul class="mt-4 divide-y divide-ink-200 rounded-xl border border-ink-200">
                        @foreach ($matches as $match)
                            <li class="flex items-center justify-between gap-4 px-5 py-4">
                                <div>
                                    <p class="font-medium">{{ $match->name }}</p>
                                    <p class="font-mono text-xs text-ink-500">{{ $match->host() }}</p>
                                </div>
                                <a href="{{ $match->url() }}/login"
                                    class="rounded-lg border border-ink-900 px-4 py-2 text-sm font-semibold transition hover:bg-ink-900 hover:text-white">
                                    Sign in
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="mt-8 rounded-xl border border-dashed border-ink-300 px-6 py-8 text-center text-sm text-ink-500">
                    Nothing found for “{{ $searched }}”. Check the spelling, or
                    <a href="{{ route('register') }}" class="font-semibold text-ae-700 hover:underline">create a shop</a>.
                </p>
            @endif
        @endisset

        <p class="mt-10 border-t border-ink-200 pt-6 text-xs text-ink-500">
            Running {{ config('tenancy.platform_name') }} itself?
            <a href="{{ '//'.config('tenancy.admin_subdomain').'.'.config('tenancy.central_domain') }}" class="font-semibold text-ink-700 hover:underline">Platform sign-in</a>.
        </p>
    </section>
</x-layouts.marketing>
