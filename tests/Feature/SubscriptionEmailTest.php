<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\PaymentMethodType;
use App\Mail\SubscriptionInvoiceRaised;
use App\Mail\SubscriptionOverdue;
use App\Mail\SubscriptionPaymentReceived;
use App\Models\Plan;
use App\Models\PlatformUser;
use App\Models\Store;
use App\Models\StoreInvoice;
use App\Services\BillingCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * What the shop is told, and when.
 */
class SubscriptionEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    protected function billing(): BillingCycle
    {
        return app(BillingCycle::class);
    }

    protected function payingStore(array $overrides = []): Store
    {
        $plan = Plan::factory()->create(['monthly_price' => 199, 'billing_period' => BillingPeriod::Monthly]);

        $this->store->update([
            'plan_id' => $plan->id,
            'billing_day' => 1,
            'grace_days' => 7,
            'prorate_first_invoice' => false,
            'auto_suspend' => true,
            'owner_email' => 'owner@example.com',
            'next_invoice_on' => today(),
            ...$overrides,
        ]);

        return $this->store->fresh();
    }

    public function test_raising_an_invoice_emails_the_owner_with_a_pay_link(): void
    {
        $this->payingStore();

        $this->billing()->generateDueInvoices();

        $invoice = StoreInvoice::firstOrFail();

        Mail::assertSent(SubscriptionInvoiceRaised::class, function (SubscriptionInvoiceRaised $mail) use ($invoice): bool {
            $rendered = $mail->render();

            return $mail->hasTo('owner@example.com')
                && $mail->invoice->is($invoice)
                && str_contains($rendered, $invoice->number)
                && str_contains($rendered, $this->store->url().'/subscription');
        });
    }

    public function test_a_receipt_is_sent_when_an_invoice_is_settled(): void
    {
        $this->payingStore();
        $this->billing()->generateDueInvoices();
        $invoice = StoreInvoice::firstOrFail();

        // A part payment is not a receipt.
        $this->billing()->recordPayment($invoice, [
            'amount' => 99,
            'method' => PaymentMethodType::BankTransfer->value,
            'received_on' => today()->toDateString(),
        ]);

        Mail::assertNotSent(SubscriptionPaymentReceived::class);

        $this->billing()->recordPayment($invoice->fresh(), [
            'amount' => 100,
            'method' => PaymentMethodType::BankTransfer->value,
            'received_on' => today()->toDateString(),
        ]);

        Mail::assertSent(SubscriptionPaymentReceived::class, fn (SubscriptionPaymentReceived $mail): bool => $mail->hasTo('owner@example.com')
            && str_contains($mail->render(), 'Payment received'));
    }

    public function test_the_overdue_warning_is_sent_once_and_says_when_the_shop_closes(): void
    {
        $store = $this->payingStore();
        $this->billing()->generateDueInvoices();

        $this->billing()->reviewOverdue(today()->addDay());

        Mail::assertSent(SubscriptionOverdue::class, function (SubscriptionOverdue $mail) use ($store): bool {
            return $mail->hasTo('owner@example.com')
                && str_contains($mail->render(), $mail->invoice->suspendOn()->format('d M Y'))
                && $store->auto_suspend;
        });

        // Run again the next night: no second warning.
        $this->billing()->reviewOverdue(today()->addDays(2));

        Mail::assertSentCount(2); // the invoice email and one overdue warning
    }

    public function test_a_store_with_no_email_address_is_simply_not_written_to(): void
    {
        $this->payingStore(['owner_email' => null, 'email' => null]);

        $this->billing()->generateDueInvoices();

        Mail::assertNothingSent();
        // The invoice is still raised; there is simply nobody to write to.
        $this->assertDatabaseCount('store_invoices', 1);
    }

    public function test_the_platform_can_send_an_invoice_again(): void
    {
        $this->payingStore();
        $this->billing()->generateDueInvoices();
        $invoice = StoreInvoice::firstOrFail();

        $this->actingAs(PlatformUser::factory()->create(), 'platform')
            ->post('http://'.config('tenancy.admin_subdomain').'.'.config('tenancy.central_domain').'/billing/'.$invoice->number.'/email')
            ->assertSessionHas('status');

        Mail::assertSent(SubscriptionInvoiceRaised::class, 2);
    }
}
