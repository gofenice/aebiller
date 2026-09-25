<x-legal-page title="Privacy Policy" :updated-on="$updatedOn"
    summary="What {{ $entity }} collects when you use {{ $platform }}, why, and what happens to it.">

    <section>
        <h2>1. Two kinds of information</h2>
        <p>
            <strong>Information about the shop.</strong> When you sign up we hold your business name, address, tax
            number, the name, email and mobile of the people who sign in, and a record of what you have been charged
            and paid. We are responsible for this information.
        </p>
        <p>
            <strong>Information the shop puts in.</strong> Products, bills, expenses and loyalty members — including a
            customer's name, mobile number, and optionally their birthday and city. This is the shop's information. We
            hold it on the shop's behalf and only act on it to run the service or when the shop asks us to.
        </p>
    </section>

    <section>
        <h2>2. Why we hold it</h2>
        <ul>
            <li>To run the service you have signed up for and keep each shop's data separate.</li>
            <li>To raise invoices, take payment and chase what is unpaid.</li>
            <li>To answer support questions and tell you about faults or important changes.</li>
            <li>To keep the service secure and to investigate misuse.</li>
            <li>To meet tax, accounting and other legal obligations.</li>
        </ul>
    </section>

    <section>
        <h2>3. Payment details</h2>
        <p>
            Card payments are handled by our payment provider, Razorpay. Card numbers are entered on their page, not
            ours: we never see or store them. We keep a record of the amount, the date, whether it succeeded, and the
            reference the provider gives us. Razorpay handles that information under its own privacy policy.
        </p>
    </section>

    <section>
        <h2>4. WhatsApp messages</h2>
        <p>
            A shop can have bills and loyalty codes sent to its customers on WhatsApp. Where it does, the customer's
            mobile number and the message content pass through the WhatsApp Business Platform, run by Meta, under the
            shop's own WhatsApp account or ours where the shop has not set one up. We keep a record of what was sent,
            to which number, and whether it was delivered.
        </p>
        <p>
            A shop must only message customers who have agreed to hear from them, and must honour a request to stop.
        </p>
    </section>

    <section>
        <h2>5. Who else sees it</h2>
        <p>We do not sell personal information. We share it only with:</p>
        <ul>
            <li>the providers who host and run the service for us, including our hosting and email providers;</li>
            <li>our payment provider, to take and reconcile payments;</li>
            <li>the WhatsApp Business Platform, where messaging is switched on;</li>
            <li>professional advisers, or authorities, where the law requires it.</li>
        </ul>
        <p>Those providers may process information outside the country where your shop operates.</p>
    </section>

    <section>
        <h2>6. How long we keep it</h2>
        <ul>
            <li>Shop records stay while the account is open.</li>
            <li>
                When an account ends, the shop is archived and kept for {{ $keepArchivedDays }} days so it can be
                restored, then deleted along with everything in it.
            </li>
            <li>Invoices and payment records are kept longer where tax or accounting law requires it.</li>
            <li>A loyalty member can be removed by the shop at any time from its own screens.</li>
        </ul>
    </section>

    <section>
        <h2>7. Security</h2>
        <p>
            Traffic is encrypted in transit. Passwords are stored hashed, and credentials a shop gives us — such as its
            WhatsApp access token — are stored encrypted. Access to production systems is limited to people who need
            it. No system is perfectly secure, so please tell us at once if you suspect a problem.
        </p>
    </section>

    <section>
        <h2>8. Your rights</h2>
        <p>
            Depending on where you are, you may ask for a copy of the information we hold about you, ask us to correct
            or delete it, or object to some uses of it. Write to us using the details below and we will respond within
            the time the law allows.
        </p>
        <p>
            If the request concerns information a shop put in about its customers, we will pass it to that shop, which
            decides what happens to its own records.
        </p>
    </section>

    <section>
        <h2>9. Cookies</h2>
        <p>
            We use cookies that are needed to make the site work: keeping you signed in and protecting forms against
            cross-site request forgery. We do not use advertising cookies or third-party tracking.
        </p>
    </section>

    <section>
        <h2>10. Changes</h2>
        <p>
            We may update this policy. The date at the top shows when it last changed, and we will tell you directly
            about a significant change.
        </p>
    </section>

    <section>
        <h2>11. Contact</h2>
        <p>
            For anything about privacy, write to
            @if ($legal['email'])
                <a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a>@if ($legal['address']), or to {{ $entity }}, {{ $legal['address'] }}@endif.
            @else
                {{ $entity }} — see the <a href="{{ route('legal.contact') }}">Contact Us</a> page.
            @endif
        </p>
    </section>
</x-legal-page>
