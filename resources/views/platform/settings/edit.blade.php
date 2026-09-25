<x-layouts.platform title="Settings">
    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">Settings</h1>
        <p class="mt-1 text-sm text-slate-500">How the platform charges its shops.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Razorpay"
                description="The account subscriptions and payment links are raised on. Keys saved here are used instead of the ones on the server.">
                <form method="POST" action="{{ route('platform.settings.update') }}" class="space-y-4 p-5">
                    @csrf
                    @method('PUT')

                    <x-field label="Key id" name="razorpay_key_id" hint="Razorpay dashboard → Account & Settings → API Keys.">
                        <x-input name="razorpay_key_id" id="razorpay_key_id" class="font-mono"
                            value="{{ old('razorpay_key_id', $settings->razorpay_key_id) }}"
                            placeholder="rzp_live_xxxxxxxxxxxx" />
                    </x-field>

                    <x-field label="Key secret" name="razorpay_key_secret"
                        :hint="$settings->razorpay_key_secret ? 'A secret is saved. Type a new one to replace it, or leave blank to keep it.' : 'Shown once by Razorpay when the key is generated. Stored encrypted.'">
                        <x-input type="password" name="razorpay_key_secret" id="razorpay_key_secret"
                            autocomplete="new-password"
                            placeholder="{{ $settings->razorpay_key_secret ? '•••••••••••••• saved' : '' }}" />
                    </x-field>

                    <x-field label="Webhook secret" name="razorpay_webhook_secret"
                        :hint="$settings->razorpay_webhook_secret ? 'A secret is saved. Type a new one to replace it.' : 'The phrase you type into Razorpay when adding the webhook below.'">
                        <x-input type="password" name="razorpay_webhook_secret" id="razorpay_webhook_secret"
                            autocomplete="new-password"
                            placeholder="{{ $settings->razorpay_webhook_secret ? '•••••••••••••• saved' : '' }}" />
                    </x-field>

                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 pt-4">
                        <x-button type="submit">Save settings</x-button>
                        <x-button type="submit" variant="secondary" form="test-razorpay">Test connection</x-button>
                    </div>
                </form>

                <form id="test-razorpay" method="POST" action="{{ route('platform.settings.razorpay.test') }}">@csrf</form>
            </x-card>

            <x-card title="Webhook" class="mt-6"
                description="Razorpay tells the platform when a card on file was charged. Without this, an automatic payment is taken but the invoice stays open.">
                <div class="space-y-4 p-5">
                    <x-field label="Endpoint URL" name="webhook_url" hint="Razorpay dashboard → Settings → Webhooks → Add New Webhook.">
                        <x-input value="{{ $webhookUrl }}" readonly class="font-mono" x-on:focus="$el.select()" />
                    </x-field>

                    <div>
                        <p class="form-label">Events to tick</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (['subscription.charged', 'subscription.activated', 'subscription.halted', 'subscription.cancelled', 'payment_link.paid'] as $event)
                                <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700">{{ $event }}</span>
                            @endforeach
                        </div>
                    </div>

                    <p class="text-xs text-slate-500">
                        Use the same secret in Razorpay as the webhook secret above, or every message is rejected as unsigned.
                    </p>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Status">
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-slate-500">Razorpay</dt>
                        <dd>
                            @if ($settings->razorpayIsReady())
                                <x-badge color="green">Keys in place</x-badge>
                            @else
                                <x-badge color="amber">Not set up</x-badge>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-slate-500">Mode</dt>
                        <dd>
                            @if (! $settings->razorpayIsReady())
                                <span class="text-slate-400">—</span>
                            @elseif ($settings->isLiveKey() || $fromEnvironment)
                                <x-badge color="red">Live — real money</x-badge>
                            @else
                                <x-badge color="blue">Test mode</x-badge>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-slate-500">Where from</dt>
                        <dd class="text-right text-xs text-slate-600">
                            {{ $fromEnvironment ? 'The server\'s .env file' : ($settings->razorpay_key_id ? 'Saved here' : 'Nowhere yet') }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-slate-500">Shops on auto-charge</dt>
                        <dd class="font-medium text-slate-800">{{ number_format($subscribedStores) }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="How it works">
                <div class="space-y-2 px-5 py-4 text-xs leading-relaxed text-slate-600">
                    <p>A shop on a monthly or yearly plan can turn on automatic payment from its own Subscription page. They authorise a card once with Razorpay.</p>
                    <p>Each period, Razorpay charges that card and tells the platform, which marks the invoice paid. Shops not on auto-charge get a payment link per invoice instead.</p>
                    <p>Plans are created on Razorpay the first time they are needed — one per plan, currency and period.</p>
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.platform>
