<x-legal-page title="Terms of Service" :updated-on="$updatedOn"
    summary="The agreement between {{ $entity }} and the shop using {{ $platform }}.">

    <section>
        <h2>1. Who this is between</h2>
        <p>
            These terms are between {{ $entity }} ("we", "us"), which operates {{ $platform }}, and the business that
            signs up for an account ("you", "the shop"). By creating a shop, or by using one that has been created for
            you, you accept these terms.
        </p>
        @if ($legal['address'])
            <p>Our registered address is {{ $legal['address'] }}.</p>
        @endif
    </section>

    <section>
        <h2>2. What the service is</h2>
        <p>
            {{ $platform }} is software you reach over the internet. It gives a shop a till, stock records, VAT
            receipts, expense tracking, a loyalty programme and reports. Each shop gets its own address and its own
            data, kept separate from every other shop on the platform.
        </p>
        <p>
            We may add, change or remove features as the product develops. Where a change would materially reduce what
            you are paying for, we will tell you before it takes effect.
        </p>
    </section>

    <section>
        <h2>3. Your account</h2>
        <ul>
            <li>You are responsible for everything done through your shop's accounts, including by your staff.</li>
            <li>Keep sign-in details private. Tell us promptly if you believe an account has been misused.</li>
            <li>The information you give us — business name, contact details, tax number — must be accurate and kept up to date.</li>
            <li>You must be entitled to enter this agreement on behalf of the business you are registering.</li>
        </ul>
    </section>

    <section>
        <h2>4. What you may not do</h2>
        <ul>
            <li>Use the service to break the law, or to store or send anything unlawful.</li>
            <li>Try to reach another shop's data, or to work around the separation between shops.</li>
            <li>Resell, rent out or white-label the service without our written agreement.</li>
            <li>Attempt to disrupt, overload, reverse engineer or copy the service.</li>
            <li>Send messages through the service to people who have not agreed to hear from you.</li>
        </ul>
    </section>

    <section>
        <h2>5. Trial, fees and payment</h2>
        <p>
            New shops may start with a free trial of {{ $trialDays }} days. After that, the plan you choose is charged
            in advance for each billing period — monthly or yearly — at the price shown when you subscribe.
        </p>
        <ul>
            <li>Invoices are raised automatically at the start of each period.</li>
            <li>You may pay each invoice yourself, or authorise a card so future periods are charged automatically.</li>
            <li>Card payments are handled by our payment provider. We do not see or store your card details.</li>
            <li>Prices exclude any tax that applies, unless the price says otherwise.</li>
            <li>If a price changes, the new price applies from your next billing period, and we will tell you first.</li>
        </ul>
        <p>
            Cancellation and refunds are covered on the <a href="{{ route('legal.refunds') }}">Refund &amp;
            Cancellation</a> page.
        </p>
    </section>

    <section>
        <h2>6. Late payment</h2>
        <p>
            If an invoice is not paid by its due date we may send reminders and, after the grace period shown on your
            subscription page, close the shop's access. A closed shop keeps its data: paying what is owed reopens it.
            If an account stays unpaid we may archive and eventually delete it, as described under Ending the agreement.
        </p>
    </section>

    <section>
        <h2>7. Your data is yours</h2>
        <p>
            The records you put into {{ $platform }} — products, bills, customers, expenses — belong to you. We hold
            them to run the service for you, and we do not sell them. What we collect and why is set out in the
            <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.
        </p>
        <p>
            You are responsible for the lawfulness of the customer information you put in, including having a basis to
            enrol someone in a loyalty programme and to message them.
        </p>
    </section>

    <section>
        <h2>8. Availability</h2>
        <p>
            We work to keep the service available and to keep backups, but we do not promise it will be uninterrupted
            or error-free. Maintenance, faults, and failures at providers we depend on can all interrupt it. Keep your
            own records of anything you cannot afford to lose.
        </p>
    </section>

    <section>
        <h2>9. Ending the agreement</h2>
        <ul>
            <li>You may cancel at any time; the shop stays open until the end of the period already paid for.</li>
            <li>We may end the agreement for a serious or repeated breach of these terms, or for non-payment.</li>
            <li>
                When an account ends we archive the shop. Archived data is kept for {{ $keepArchivedDays }} days so it
                can be restored, and is deleted after that. Ask us within that window if you want an export.
            </li>
        </ul>
    </section>

    <section>
        <h2>10. Liability</h2>
        <p>
            Nothing here excludes liability that cannot lawfully be excluded. Beyond that, we are not liable for lost
            profits, lost sales, lost or corrupted data, or indirect or consequential loss. Our total liability for any
            claim is limited to the fees you paid us in the twelve months before the claim arose.
        </p>
        <p>
            The service is a tool for running a shop. It does not give tax, accounting or legal advice, and you remain
            responsible for the correctness of the invoices, tax figures and records you produce with it.
        </p>
    </section>

    <section>
        <h2>11. Changes to these terms</h2>
        <p>
            We may update these terms. The date at the top shows when they last changed, and continuing to use the
            service after a change means you accept it. Where a change is significant, we will tell you directly.
        </p>
    </section>

    <section>
        <h2>12. Law</h2>
        <p>
            @if ($legal['jurisdiction'])
                This agreement is governed by the laws of {{ $legal['jurisdiction'] }}, and its courts have exclusive
                jurisdiction over any dispute.
            @else
                This agreement is governed by the laws of the place where {{ $entity }} is established, and its courts
                have exclusive jurisdiction over any dispute.
            @endif
        </p>
    </section>

    <section>
        <h2>13. Getting in touch</h2>
        <p>
            Questions about these terms go to
            @if ($legal['email'])
                <a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a>,
            @endif
            or see the <a href="{{ route('legal.contact') }}">Contact Us</a> page.
        </p>
    </section>
</x-legal-page>
