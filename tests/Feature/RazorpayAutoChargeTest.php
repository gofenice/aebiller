<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Models\Plan;
use App\Models\Store;
use App\Models\StoreInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A card left on file with Razorpay, charged each period without anyone
 * having to remember.
 */
class RazorpayAutoChargeTest extends TestCase
{
    protected const WEBHOOK_SECRET = 'test-webhook-secret';

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'test-api-secret',
            'services.razorpay.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    protected function subscribingStore(BillingPeriod $period = BillingPeriod::Monthly): Store
    {
        $plan = Plan::factory()->create(['monthly_price' => 199, 'billing_period' => $period, 'currency_code' => 'SAR']);

        $this->store->update([
            'plan_id' => $plan->id,
            'billing_day' => 1,
            'prorate_first_invoice' => false,
            'next_invoice_on' => today(),
            'owner_email' => 'owner@example.com',
        ]);

        return $this->store->fresh();
    }

    protected function webhook(array $payload): TestResponse
    {
        $body = json_encode($payload);

        return $this->call('POST', 'http://'.config('tenancy.central_domain').'/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $body, self::WEBHOOK_SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_setting_up_a_card_creates_the_plan_and_subscription_at_razorpay(): void
    {
        $store = $this->subscribingStore();

        Http::fake([
            '*/plans' => Http::response(['id' => 'plan_RZP1']),
            '*/subscriptions' => Http::response(['id' => 'sub_RZP1', 'status' => 'created', 'short_url' => 'https://rzp.io/s/sub1']),
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('subscription.auto-charge.enable'))
            ->assertRedirect('https://rzp.io/s/sub1');

        $store->refresh();

        $this->assertSame('sub_RZP1', $store->razorpay_subscription_id);
        $this->assertSame('plan_RZP1', $store->plan->fresh()->razorpay_plan_id);
        // Not on until Razorpay says the card was authorised.
        $this->assertFalse($store->autoChargeIsRunning());
        $this->assertTrue($store->autoChargeIsPending());

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/subscriptions')
            && $request['plan_id'] === 'plan_RZP1'
            && $request['total_count'] === 120);
    }

    public function test_a_yearly_plan_asks_razorpay_for_a_yearly_cycle(): void
    {
        $this->subscribingStore(BillingPeriod::Yearly);

        Http::fake([
            '*/plans' => Http::response(['id' => 'plan_YEAR']),
            '*/subscriptions' => Http::response(['id' => 'sub_YEAR', 'status' => 'created', 'short_url' => 'https://rzp.io/s/year']),
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())->post(route('subscription.auto-charge.enable'));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/plans') && $request['period'] === 'yearly');
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/subscriptions') && $request['total_count'] === 10);
    }

    public function test_a_lifetime_plan_cannot_be_put_on_automatic_payment(): void
    {
        $this->subscribingStore(BillingPeriod::Lifetime);

        Http::fake();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('subscription.auto-charge.enable'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_authorising_the_card_switches_automatic_payment_on(): void
    {
        $store = $this->subscribingStore();
        $store->update(['razorpay_subscription_id' => 'sub_RZP1', 'razorpay_subscription_status' => 'created']);

        $this->webhook([
            'event' => 'subscription.activated',
            'payload' => ['subscription' => ['entity' => ['id' => 'sub_RZP1', 'status' => 'active']]],
        ])->assertOk();

        $store->refresh();

        $this->assertTrue($store->auto_charge_enabled);
        $this->assertTrue($store->autoChargeIsRunning());
    }

    public function test_a_charge_settles_the_invoice_and_raises_one_if_needed(): void
    {
        $store = $this->subscribingStore();
        $store->update(['razorpay_subscription_id' => 'sub_RZP1', 'razorpay_subscription_status' => 'active', 'auto_charge_enabled' => true]);

        // No invoice exists yet: Razorpay charges on its own schedule.
        $this->assertDatabaseCount('store_invoices', 0);

        $this->webhook([
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_RZP1', 'status' => 'active']],
                'payment' => ['entity' => ['id' => 'pay_AUTO1']],
            ],
        ])->assertOk();

        $invoice = StoreInvoice::firstOrFail();

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('pay_AUTO1', $invoice->razorpay_payment_id);
        $this->assertDatabaseHas('store_payments', ['reference' => 'pay_AUTO1', 'amount' => 199]);
    }

    public function test_the_same_charge_reported_twice_is_recorded_once(): void
    {
        $store = $this->subscribingStore();
        $store->update(['razorpay_subscription_id' => 'sub_RZP1', 'razorpay_subscription_status' => 'active', 'auto_charge_enabled' => true]);

        $event = [
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_RZP1', 'status' => 'active']],
                'payment' => ['entity' => ['id' => 'pay_AUTO1']],
            ],
        ];

        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk();

        $this->assertDatabaseCount('store_payments', 1);
    }

    public function test_a_halted_subscription_turns_automatic_payment_off(): void
    {
        $store = $this->subscribingStore();
        $store->update(['razorpay_subscription_id' => 'sub_RZP1', 'razorpay_subscription_status' => 'active', 'auto_charge_enabled' => true]);

        $this->webhook([
            'event' => 'subscription.halted',
            'payload' => ['subscription' => ['entity' => ['id' => 'sub_RZP1', 'status' => 'halted']]],
        ])->assertOk();

        $store->refresh();

        $this->assertFalse($store->auto_charge_enabled);
        $this->assertFalse($store->autoChargeIsRunning());
        $this->assertFalse($store->autoChargeIsPending(), 'a halted card is not waiting to be authorised');
    }

    public function test_the_shop_can_turn_automatic_payment_off(): void
    {
        $store = $this->subscribingStore();
        $store->update(['razorpay_subscription_id' => 'sub_RZP1', 'razorpay_subscription_status' => 'active', 'auto_charge_enabled' => true]);

        Http::fake(['*/cancel' => Http::response(['status' => 'cancelled'])]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('subscription.auto-charge.disable'))
            ->assertSessionHas('status');

        $store->refresh();

        $this->assertFalse($store->auto_charge_enabled);
        $this->assertSame('cancelled', $store->razorpay_subscription_status);
    }

    public function test_a_cashier_cannot_set_up_or_cancel_automatic_payment(): void
    {
        $this->subscribingStore();

        $cashier = User::factory()->create();

        $this->actingAs($cashier)->post(route('subscription.auto-charge.enable'))->assertForbidden();
        $this->actingAs($cashier)->delete(route('subscription.auto-charge.disable'))->assertForbidden();
    }
}
