<x-layouts.marketing title="Your shop is ready">
    <section class="mx-auto max-w-2xl px-4 py-20 sm:px-6">
        <span class="ae-rule"></span>
        <h1 class="mt-6 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $store->name }} is ready</h1>
        <p class="mt-3 text-lg text-ink-600">
            Your shop is live and free until {{ $trialEndsOn?->format('d M Y') }}.
        </p>

        <div class="mt-8 rounded-2xl border border-ink-200 bg-ink-50 p-7">
            <p class="ae-label">Your address</p>
            <p class="mt-2 font-mono text-lg font-semibold break-all">{{ $store->host() }}</p>
            <p class="mt-2 text-sm text-ink-600">
                Bookmark it. This is where you and your staff sign in — with the email and password you just chose.
            </p>

            <a href="{{ $signInUrl }}"
                class="mt-6 inline-block rounded-lg bg-ae-600 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-ae-500">
                Open my shop
            </a>
            <p class="mt-2 text-xs text-ink-500">This link signs you in once and lasts an hour.</p>
        </div>

        <div class="mt-10">
            <p class="ae-label">What to do first</p>
            <ol class="mt-5 space-y-4 text-[15px] text-ink-700">
                @foreach ([
                    'Add a handful of products — or one, to try a sale end to end.',
                    'Receive some stock so the shelves have numbers behind them.',
                    'Ring up a test bill and print the receipt.',
                    'Switch the loyalty programme on when you are ready for cards.',
                ] as $index => $step)
                    <li class="flex gap-4">
                        <span class="font-mono text-xs font-semibold text-ae-600">0{{ $index + 1 }}</span>
                        {{ $step }}
                    </li>
                @endforeach
            </ol>
        </div>

        <p class="mt-10 border-t border-ink-200 pt-6 text-sm text-ink-500">
            We have emailed nothing yet — your invoice arrives the day before the trial ends, at
            <span class="font-medium text-ink-700">{{ $store->owner_email }}</span>.
        </p>
    </section>
</x-layouts.marketing>
