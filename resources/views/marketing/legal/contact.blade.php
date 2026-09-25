<x-legal-page title="Contact Us" :updated-on="$updatedOn"
    summary="How to reach {{ $entity }} about {{ $platform }}.">

    <section>
        <h2>Who we are</h2>
        <p>{{ $entity }} runs {{ $platform }}, billing and stock software for small supermarkets and grocery shops.</p>
    </section>

    <section>
        <h2>How to reach us</h2>
        <dl class="space-y-4">
            @if ($legal['email'])
                <div>
                    <dt class="text-sm font-semibold text-ink-900">Email</dt>
                    <dd><a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a></dd>
                </div>
            @endif

            @if ($legal['phone'])
                <div>
                    <dt class="text-sm font-semibold text-ink-900">Phone</dt>
                    <dd><a href="tel:{{ preg_replace('/\s+/', '', $legal['phone']) }}">{{ $legal['phone'] }}</a></dd>
                </div>
            @endif

            @if ($legal['address'])
                <div>
                    <dt class="text-sm font-semibold text-ink-900">Address</dt>
                    <dd>{{ $legal['address'] }}</dd>
                </div>
            @endif

            @if ($legal['hours'])
                <div>
                    <dt class="text-sm font-semibold text-ink-900">Support hours</dt>
                    <dd>{{ $legal['hours'] }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <section>
        <h2>Already have a shop?</h2>
        <p>
            Sign in at <a href="{{ route('customer.find') }}">{{ config('tenancy.app_subdomain') }}.{{ config('tenancy.central_domain') }}</a>,
            or go straight to your shop's own address. Billing questions are answered fastest with your shop's address
            and the invoice number to hand.
        </p>
    </section>

    <section>
        <h2>Want to start one?</h2>
        <p>
            <a href="{{ route('register') }}">Create a shop</a> and try it free for {{ $trialDays }} days. No card is
            needed to start.
        </p>
    </section>
</x-legal-page>
