<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethodType;
use App\Models\Plan;
use App\Models\StoreInvoice;
use App\Models\User;
use App\Services\BillingCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Paying a subscription through Razorpay. Nothing is trusted because it
 * arrived — only because it is signed.
 */
class RazorpayPaymentTest extends TestCase
{
    protected const SECRET = 'test-api-secret';

    protected const WEBHOOK_SECRET = 'test-webhook-secret';

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => self::SECRET,
            'services.razorpay.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    protected function invoice(): StoreInvoice
    {
        // Priced in riyals on purpose: these tests check the amount is converted
        // to the smallest unit of whatever currency the shop is billed in.
        $plan = Plan::factory()->create([
            'monthly_price' => 199,
            'billing_period' => BillingPeriod::Monthly,
            'currency_code' => 'SAR',
        ]);

        $this->store->update([
            'plan_id' => $plan->id,
            'billing_day' => 1,
            // A whole month, so the figures here are the plan price.
            'prorate_first_invoice' => false,
            'next_invoice_on' => today(),
            'owner_email' => 'owner@example.com',
        ]);

        app(BillingCycle::class)->generateDueInvoices();

        return StoreInvoice::firstOrFail();
    }

    /**
     * @param  array<string, string>  $params
     */
    protected function sign(array $params): string
    {
        return hash_hmac('sha256', implode('|', [
            $params['razorpay_payment_link_id'],
            $params['razorpay_payment_link_reference_id'],
            $params['razorpay_payment_link_status'],
            $params['razorpay_payment_id'],
        ]), self::SECRET);
    }

    public function test_the_owner_is_sent_to_a_razorpay_payment_link(): void
    {
        $invoice = $this->invoice();

        Http::fake([
            'api.razorpay.com/*' => Http::response(['id' => 'plink_ABC123', 'short_url' => 'https://rzp.io/i/abc123']),
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('subscription.pay', $invoice))
            ->assertRedirect('https://rzp.io/i/abc123');

        $invoice->refresh();
        $this->assertSame('plink_ABC123', $invoice->razorpay_payment_link_id);

        // The amount is sent in the smallest unit of the currency.
        Http::assertSent(fn ($request): bool => $request['amount'] === 19900
            && $request['currency'] === 'SAR'
            && $request['reference_id'] === $invoice->number);
    }

    public function test_the_same_link_is_reused_rather_than_raised_again(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['razorpay_payment_link_id' => 'plink_OLD', 'razorpay_short_url' => 'https://rzp.io/i/old']);

        Http::fake();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('subscription.pay', $invoice))
            ->assertRedirect('https://rzp.io/i/old');

        Http::assertNothingSent();
    }

    public function test_a_cashier_cannot_pay_the_subscription(): void
    {
        $invoice = $this->invoice();

        $this->actingAs(User::factory()->create())
            ->post(route('subscription.pay', $invoice))
            ->assertForbidden();
    }

    public function test_a_signed_callback_settles_the_invoice(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['razorpay_payment_link_id' => 'plink_ABC123']);

        $params = [
            'razorpay_payment_link_id' => 'plink_ABC123',
            'razorpay_payment_link_reference_id' => $invoice->number,
            'razorpay_payment_link_status' => 'paid',
            'razorpay_payment_id' => 'pay_XYZ789',
        ];

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('subscription.callback', [...$params, 'razorpay_signature' => $this->sign($params)]))
            ->assertRedirect(route('dashboard'));

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('pay_XYZ789', $invoice->razorpay_payment_id);
        $this->assertDatabaseHas('store_payments', [
            'store_invoice_id' => $invoice->id,
            'method' => PaymentMethodType::Razorpay->value,
            'reference' => 'pay_XYZ789',
        ]);
    }

    public function test_a_forged_callback_changes_nothing(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['razorpay_payment_link_id' => 'plink_ABC123']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('subscription.callback', [
                'razorpay_payment_link_id' => 'plink_ABC123',
                'razorpay_payment_link_reference_id' => $invoice->number,
                'razorpay_payment_link_status' => 'paid',
                'razorpay_payment_id' => 'pay_FORGED',
                'razorpay_signature' => 'not-a-real-signature',
            ]))
            ->assertRedirect(route('subscription.show'))
            ->assertSessionHas('error');

        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
        $this->assertDatabaseCount('store_payments', 0);
    }

    public function test_the_webhook_settles_an_invoice_even_if_the_browser_closed(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['razorpay_payment_link_id' => 'plink_ABC123']);

        $payload = json_encode([
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => ['entity' => ['id' => 'plink_ABC123', 'reference_id' => $invoice->number]],
                'payment' => ['entity' => ['id' => 'pay_HOOK1']],
            ],
        ]);

        $this->call('POST', 'http://'.config('tenancy.central_domain').'/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $payload, self::WEBHOOK_SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_an_unsigned_webhook_is_refused(): void
    {
        $invoice = $this->invoice();
        $payload = json_encode(['event' => 'payment_link.paid']);

        $this->call('POST', 'http://'.config('tenancy.central_domain').'/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => 'wrong',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(400);

        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
    }

    public function test_a_payment_reported_twice_is_only_recorded_once(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['razorpay_payment_link_id' => 'plink_ABC123']);

        $billing = app(BillingCycle::class);
        $billing->settleFromGateway($invoice->fresh(), 'pay_TWICE');
        $billing->settleFromGateway($invoice->fresh(), 'pay_TWICE');

        $this->assertDatabaseCount('store_payments', 1);
        $this->assertSame(199.00, (float) $invoice->fresh()->amount_paid);
    }

    public function test_without_keys_the_shop_is_told_to_pay_by_transfer(): void
    {
        config(['services.razorpay.key' => null, 'services.razorpay.secret' => null]);

        $this->invoice();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('subscription.show'))
            ->assertOk()
            ->assertSee('Online payment is not switched on')
            ->assertDontSee('Pay SAR');
    }
}
