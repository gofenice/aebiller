<x-layouts.app title="Old due book">
    @php
        $symbol = config('inventory.currency_symbol');
    @endphp

    <x-page-header title="Old due book"
        description="What customers already owed before the shop billed here. Type a line, save, and the next one is ready.">
        <x-slot:actions>
            <x-button :href="route('reports.credit')" variant="secondary">Credit Due report</x-button>
            <x-button :href="route('customers.index', ['status' => 'owing'])" variant="secondary">Everyone who owes</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Add a line"
                description="A customer who is not on the books yet is signed up as you enter them. A number already registered has its balance set instead.">
                <form method="POST" action="{{ route('customers.due-book.store') }}" class="space-y-4 p-5">
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-6">
                        <x-field class="sm:col-span-3" label="Customer name" name="name" required>
                            {{-- Focus returns here after every save, so the book
                                 can be typed straight through. --}}
                            <x-input name="name" id="name" value="{{ old('name') }}" required autofocus
                                placeholder="e.g. Aisha Rahman" />
                        </x-field>

                        <x-field class="sm:col-span-3" label="Mobile number" name="phone" required
                            hint="How they are found at the till, and how the balance is kept against them.">
                            <x-input name="phone" id="phone" value="{{ old('phone') }}" required
                                inputmode="numeric" placeholder="0551234567" />
                        </x-field>

                        <x-field class="sm:col-span-2" label="Amount owed" name="amount" required
                            :hint="'In '.trim($symbol).'. The whole balance, not a part payment.'">
                            <x-input type="number" step="0.01" min="0.01" name="amount" id="amount"
                                value="{{ old('amount') }}" required class="text-right" placeholder="0.00" />
                        </x-field>

                        <x-field class="sm:col-span-2" label="Owed as at" name="opening_due_on">
                            <x-input type="date" name="opening_due_on" id="opening_due_on"
                                value="{{ old('opening_due_on', now()->toDateString()) }}" />
                        </x-field>

                        <x-field class="sm:col-span-2" label="Note" name="opening_due_note">
                            <x-input name="opening_due_note" id="opening_due_note"
                                value="{{ old('opening_due_note', 'From the old book') }}" />
                        </x-field>
                    </div>

                    <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
                        <x-button type="submit">Save &amp; add another</x-button>
                        <p class="form-hint">
                            Collect what is owed from the customer's own page, or from the Credit Due report.
                        </p>
                    </div>
                </form>
            </x-card>
        </div>

        <div>
            <x-card title="Entered so far">
                <div class="border-b border-slate-200 px-5 py-4">
                    <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Carried over, still owed</p>
                    <p class="mt-1 text-2xl font-semibold text-red-600">{{ $symbol }}{{ number_format($carriedOver, 2) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $owing->count() }} customer(s)</p>
                </div>

                @if ($owing->isEmpty())
                    <p class="px-5 py-4 text-sm text-slate-500">Nothing entered yet.</p>
                @else
                    <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                        @foreach ($owing as $member)
                            <li>
                                <a href="{{ route('customers.show', $member) }}"
                                    class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm hover:bg-slate-50/70">
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium text-slate-900">{{ $member->name }}</span>
                                        <span class="text-xs text-slate-400">{{ $member->formattedPhone() }}</span>
                                    </span>
                                    <span class="shrink-0 font-semibold text-red-600">
                                        {{ $symbol }}{{ number_format((float) $member->opening_due_outstanding, 2) }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
