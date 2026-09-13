<x-layouts.marketing title="Create your shop" description="Sign your shop up and start billing today.">
    @php $central = config('tenancy.central_domain'); @endphp

    <section class="mx-auto grid max-w-6xl gap-12 px-4 py-14 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:py-20">
        <div>
            <span class="ae-rule"></span>
            <h1 class="mt-6 text-3xl font-semibold tracking-tight sm:text-4xl">Create your shop</h1>
            <p class="mt-3 text-ink-600">
                {{ $trialDays }} days free, no card. Your shop is ready as soon as you finish this form.
            </p>

            <x-alerts />

            <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-8"
                x-data="{
                    name: {{ Js::from(old('name')) }},
                    slug: {{ Js::from(old('slug')) }},
                    touched: {{ old('slug') ? 'true' : 'false' }},
                    state: null,
                    checking: false,
                    timer: null,

                    suggest() {
                        if (! this.touched) {
                            this.slug = this.clean(this.name);
                        }
                        this.check();
                    },

                    clean(value) {
                        return (value || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
                    },

                    check() {
                        clearTimeout(this.timer);
                        this.state = null;

                        if (this.slug.length < 3) {
                            return;
                        }

                        this.checking = true;
                        this.timer = setTimeout(async () => {
                            try {
                                const response = await fetch(`{{ route('register.availability') }}?slug=${encodeURIComponent(this.slug)}`, {
                                    headers: { Accept: 'application/json' },
                                });
                                this.state = response.ok ? await response.json() : null;
                            } catch (error) {
                                this.state = null;
                            } finally {
                                this.checking = false;
                            }
                        }, 300);
                    },
                }">
                @csrf

                <fieldset class="space-y-5">
                    <legend class="mb-4 text-xs font-semibold tracking-[0.2em] text-ink-500 uppercase">The shop</legend>

                    <div>
                        <label for="name" class="form-label">Shop name</label>
                        <input type="text" name="name" id="name" required maxlength="120" class="form-input"
                            placeholder="e.g. Al Noor Super Market" x-model="name" x-on:input="suggest()">
                        @error('name') <p class="mt-1 text-xs font-medium text-ae-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="slug" class="form-label">Your address</label>
                        {{-- min-w-0 / shrink-0: on a narrow phone the field gives
                             way first, rather than the suffix pushing the row wide. --}}
                        <div class="flex items-stretch">
                            <input type="text" name="slug" id="slug" required minlength="3" maxlength="40"
                                class="form-input min-w-0 rounded-r-none font-mono" placeholder="al-noor"
                                x-model="slug" x-on:input="touched = true; slug = clean(slug); check()">
                            <span class="flex shrink-0 items-center rounded-r-lg border border-l-0 border-slate-300 bg-ink-50 px-3 font-mono text-sm whitespace-nowrap text-ink-500">
                                .{{ $central }}
                            </span>
                        </div>
                        <p class="form-hint" x-show="! state && ! checking" x-cloak>
                            This is where you and your staff will sign in every day.
                        </p>
                        <p class="mt-1 text-xs text-ink-400" x-show="checking" x-cloak>Checking…</p>
                        <p class="mt-1 text-xs font-medium" x-show="state && ! checking" x-cloak
                            x-bind:class="state?.available ? 'text-emerald-600' : 'text-ae-700'">
                            <span x-text="state?.available ? '✓ ' + state.host + ' is free' : '✕ ' + state?.reason"></span>
                        </p>
                        @error('slug') <p class="mt-1 text-xs font-medium text-ae-700">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="currency_code" class="form-label">Currency</label>
                            {{-- Not $currency: that holds the currency prices are
                                 quoted in, and a foreach variable outlives its loop. --}}
                            <select name="currency_code" id="currency_code" class="form-input pr-8">
                                @foreach (config('tenancy.currencies') as $code => $option)
                                    <option value="{{ $code }}" @selected(old('currency_code', config('tenancy.defaults.currency_code')) === $code)>
                                        {{ $code }} — {{ $option['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="timezone" class="form-label">Time zone</label>
                            <select name="timezone" id="timezone" class="form-input pr-8">
                                @foreach (config('tenancy.timezones') as $timezone)
                                    <option value="{{ $timezone }}" @selected(old('timezone', config('tenancy.defaults.timezone')) === $timezone)>{{ $timezone }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="space-y-5">
                    <legend class="mb-4 text-xs font-semibold tracking-[0.2em] text-ink-500 uppercase">You</legend>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="owner_name" class="form-label">Your name</label>
                            <input type="text" name="owner_name" id="owner_name" required maxlength="120" class="form-input"
                                value="{{ old('owner_name') }}">
                            @error('owner_name') <p class="mt-1 text-xs font-medium text-ae-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="owner_phone" class="form-label">Mobile <span class="font-normal text-ink-400">(optional)</span></label>
                            <input type="tel" name="owner_phone" id="owner_phone" maxlength="20" class="form-input"
                                value="{{ old('owner_phone') }}" placeholder="055 123 4567">
                        </div>
                    </div>

                    <div>
                        <label for="owner_email" class="form-label">Email</label>
                        <input type="email" name="owner_email" id="owner_email" required maxlength="150" class="form-input"
                            value="{{ old('owner_email') }}" autocomplete="username">
                        <p class="form-hint">You will sign in with this, and invoices are sent here.</p>
                        @error('owner_email') <p class="mt-1 text-xs font-medium text-ae-700">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" required class="form-input" autocomplete="new-password">
                            @error('password') <p class="mt-1 text-xs font-medium text-ae-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="form-label">Repeat password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" required class="form-input" autocomplete="new-password">
                        </div>
                    </div>
                </fieldset>

                @if ($plans->isNotEmpty())
                    <fieldset>
                        <legend class="mb-4 text-xs font-semibold tracking-[0.2em] text-ink-500 uppercase">Plan after the trial</legend>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($plans as $plan)
                                @php $price = $plan->amountIn($currency); @endphp
                                <label class="cursor-pointer">
                                    <input type="radio" name="plan" value="{{ $plan->slug }}" class="peer sr-only"
                                        @checked(old('plan', $chosenPlan ?: $plans->first()->slug) === $plan->slug)>
                                    <span class="block rounded-xl border border-ink-200 px-4 py-3 transition peer-checked:border-ae-600 peer-checked:bg-ae-50 hover:border-ink-300">
                                        <span class="flex items-center justify-between gap-3">
                                            <span class="font-semibold">{{ $plan->name }}</span>
                                            <span class="text-sm font-semibold">{{ \App\Support\DisplayCurrency::format($price['amount'], $price['currency']) }}</span>
                                        </span>
                                        <span class="mt-0.5 block text-xs text-ink-500">{{ $plan->billing_period->label() }} · nothing to pay for {{ $trialDays }} days</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('plan') <p class="mt-1 text-xs font-medium text-ae-700">{{ $message }}</p> @enderror
                    </fieldset>
                @endif

                <label class="flex cursor-pointer items-start gap-3 text-sm text-ink-600">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms'))
                        class="mt-0.5 size-4 shrink-0 rounded border-slate-300 text-ae-600 focus:ring-ae-500">
                    <span>I understand the trial lasts {{ $trialDays }} days, after which the shop is invoiced for the plan above.</span>
                </label>
                @error('terms') <p class="-mt-4 text-xs font-medium text-ae-700">{{ $message }}</p> @enderror

                <button type="submit"
                    class="w-full rounded-lg bg-ae-600 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-ae-500 sm:w-auto">
                    Create my shop
                </button>
            </form>
        </div>

        <aside class="lg:pt-24">
            <div class="sticky top-28 rounded-2xl border border-ink-200 bg-ink-50 p-7">
                <p class="ae-label">What you get today</p>
                <ul class="mt-5 space-y-4 text-sm text-ink-700">
                    @foreach ([
                        'Your own address, working immediately',
                        'Till, stock, expenses and reports',
                        'Loyalty programme with printable cards',
                        'Units, categories and tiers already set up',
                        'Unlimited staff accounts',
                    ] as $item)
                        <li class="flex gap-3">
                            <span class="mt-1.5 h-2 w-2 shrink-0 -skew-x-[25deg] bg-ae-600"></span>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>

                <p class="mt-6 border-t border-ink-200 pt-5 text-xs leading-relaxed text-ink-500">
                    Already have a shop with us?
                    <a href="{{ route('customer.find') }}" class="font-semibold text-ae-700 hover:underline">Sign in here</a>.
                </p>
            </div>
        </aside>
    </section>
</x-layouts.marketing>
