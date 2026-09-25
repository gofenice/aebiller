<x-legal-page title="Refund & Cancellation Policy" :updated-on="$updatedOn"
    summary="How to cancel a {{ $platform }} subscription, and when money comes back.">

    <section>
        <h2>1. Try it before you pay</h2>
        <p>
            Every new shop can use {{ $platform }} free for {{ $trialDays }} days. Nothing is charged during the trial,
            and no card is needed to start one. If the software is not right for your shop, walk away before the trial
            ends and you will not be billed.
        </p>
    </section>

    <section>
        <h2>2. Cancelling</h2>
        <ul>
            <li>You can cancel at any time from the Subscription page inside your shop, or by writing to us.</li>
            <li>Cancelling stops the next charge. Your shop stays open until the end of the period you have paid for.</li>
            <li>If you have authorised a card for automatic payment, cancelling stops that too.</li>
            <li>Turning off automatic payment on its own does not cancel the subscription — invoices still fall due.</li>
        </ul>
    </section>

    <section>
        <h2>3. Refunds</h2>
        <p>
            Fees are charged in advance for a period. As a rule we do not refund part of a period you have already
            started, because the service stayed available to you throughout it.
        </p>
        <p>We do refund in these cases:</p>
        <ul>
            <li><strong>Charged in error</strong> — a duplicate charge, or a charge after you cancelled. Refunded in full.</li>
            <li><strong>Charged after a failed sign-up</strong> — where a shop was never provisioned. Refunded in full.</li>
            <li>
                <strong>A fault that stopped you working</strong> — where the service was unavailable for a long period
                through our fault and we could not put it right. Refunded in proportion to the time lost.
            </li>
        </ul>
        <p>
            Outside those cases we may still refund at our discretion, particularly on a yearly plan cancelled soon
            after it renewed.
        </p>
    </section>

    <section>
        <h2>4. How to ask</h2>
        <p>
            Write to
            @if ($legal['email'])
                <a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a>
            @else
                us using the details on the <a href="{{ route('legal.contact') }}">Contact Us</a> page
            @endif
            with your shop's address and the invoice number. We aim to reply within two working days and to decide
            within five.
        </p>
    </section>

    <section>
        <h2>5. How a refund is paid</h2>
        <p>
            An approved refund goes back by the way it was paid, to the same card or account. Once we have sent it,
            banks usually take five to ten working days to show it. We do not refund to a different account.
        </p>
    </section>

    <section>
        <h2>6. Nothing is shipped</h2>
        <p>
            {{ $platform }} is software delivered over the internet. There is nothing to post, so no delivery charges
            or shipping timelines apply. Access to a shop is available as soon as it is created.
        </p>
    </section>

    <section>
        <h2>7. Your data when you leave</h2>
        <p>
            Cancelling does not delete your records at once. The shop is archived and kept for
            {{ $keepArchivedDays }} days, so you can come back or ask us for an export, and is deleted after that.
        </p>
    </section>
</x-legal-page>
