<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethodType;
use App\Enums\StoreStatus;
use App\Models\Plan;
use App\Models\PlatformUser;
use App\Models\Store;
use App\Models\StoreInvoice;
use App\Models\User;
use App\Services\BillingCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Monthly subscriptions: invoices raised per store, payments recorded by hand,
 * and the warn-then-suspend rule when a store stops paying.
 */
class PlatformBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function billing(): BillingCycle
    {
        return app(BillingCycle::class);
    }

    protected function platformUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.admin_subdomain').'.'.config('tenancy.central_domain').$path;
    }

    /**
     * A store on a plan, due to be invoiced today.
     */
    protected function payingStore(array $overrides = []): Store
    {
        $plan = Plan::factory()->create(['monthly_price' => 199, 'currency_code' => 'SAR']);

        $this->store->update([
            'plan_id' => $plan->id,
            'billing_day' => 1,
            'grace_days' => 7,
            'auto_suspend' => true,
            // Whole periods here; part months are covered in ProRataBillingTest.
            'prorate_first_invoice' => false,
            'next_invoice_on' => today(),
            ...$overrides,
        ]);

        return $this->store->fresh();
    }

    public function test_an_invoice_is_raised_for_a_store_that_is_due(): void
    {
        $store = $this->payingStore();

        $result = $this->billing()->generateDueInvoices();

        $invoice = StoreInvoice::firstOrFail();

        $this->assertSame(1, $result['invoices']);
        $this->assertSame(199.00, (float) $invoice->amount);
        $this->assertSame('SAR', $invoice->currency_code);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame($store->id, $invoice->store_id);
        // The next one falls on the store's billing day — the 1st of next month.
        $this->assertTrue($store->fresh()->next_invoice_on->isSameDay(today()->startOfMonth()->addMonthNoOverflow()));
    }

    public function test_a_store_fee_overrides_the_plan_price(): void
    {
        $this->payingStore(['monthly_fee' => 149, 'billing_currency' => 'AED']);

        $this->billing()->generateDueInvoices();

        $invoice = StoreInvoice::firstOrFail();

        $this->assertSame(149.00, (float) $invoice->amount);
        $this->assertSame('AED', $invoice->currency_code);
    }

    public function test_the_same_month_is_never_invoiced_twice(): void
    {
        $this->payingStore();

        $this->billing()->generateDueInvoices();
        // Put the date back as though the job ran twice for the same month.
        $this->store->fresh()->update(['next_invoice_on' => today()]);
        $this->billing()->generateDueInvoices();

        $this->assertDatabaseCount('store_invoices', 1);
    }

    public function test_a_store_with_nothing_to_pay_is_not_invoiced(): void
    {
        $this->store->update(['plan_id' => null, 'monthly_fee' => null, 'next_invoice_on' => today()]);

        $this->assertSame(0, $this->billing()->generateDueInvoices()['invoices']);
        $this->assertDatabaseCount('store_invoices', 0);
    }

    public function test_recording_a_payment_settles_the_invoice(): void
    {
        $this->payingStore();
        $this->billing()->generateDueInvoices();
        $invoice = StoreInvoice::firstOrFail();
        $admin = PlatformUser::factory()->create();

        // A part payment first.
        $this->actingAs($admin, 'platform')
            ->post($this->platformUrl('/billing/'.$invoice->number.'/payments'), [
                'amount' => 99,
                'method' => PaymentMethodType::BankTransfer->value,
                'reference' => 'TRF-001',
                'received_on' => today()->toDateString(),
            ])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PartlyPaid, $invoice->status);
        $this->assertSame(100.00, $invoice->outstanding());

        $this->actingAs($admin, 'platform')
            ->post($this->platformUrl('/billing/'.$invoice->number.'/payments'), [
                'amount' => 100,
                'method' => PaymentMethodType::Cash->value,
                'received_on' => today()->toDateString(),
            ])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(0.0, $invoice->outstanding());
        $this->assertNotNull($invoice->paid_at);
        $this->assertDatabaseCount('store_payments', 2);
    }

    public function test_an_unpaid_invoice_goes_overdue_then_closes_the_store(): void
    {
        $store = $this->payingStore();
        $this->billing()->generateDueInvoices();

        // The day after it fell due: overdue, but the shop still works.
        $result = $this->billing()->reviewOverdue(today()->addDay());
        $this->assertSame(1, $result['overdue']);
        $this->assertSame(0, $result['suspended']);
        $this->assertSame(StoreStatus::Active, $store->fresh()->status);

        // Once the grace period has run out, it is closed.
        $result = $this->billing()->reviewOverdue(today()->addDays(9));
        $this->assertSame(1, $result['suspended']);
        $this->assertSame(StoreStatus::Suspended, $store->fresh()->status);

        // The shop can do nothing but pay.
        $owner = User::factory()->superAdmin()->create();
        $host = 'http://'.$store->host();

        $this->actingAs($owner)->get($host.'/products')->assertRedirect($host.'/subscription');
        $this->actingAs($owner)->get($host.'/subscription')->assertOk()->assertSee('locked until this is paid');
    }

    public function test_a_store_that_opted_out_of_auto_suspend_is_only_warned(): void
    {
        $store = $this->payingStore(['auto_suspend' => false]);
        $this->billing()->generateDueInvoices();

        $this->billing()->reviewOverdue(today()->addDays(30));

        $this->assertSame(StoreStatus::Active, $store->fresh()->status);
        $this->assertSame(InvoiceStatus::Overdue, StoreInvoice::firstOrFail()->status);
    }

    public function test_paying_what_is_owed_reopens_a_closed_store(): void
    {
        $store = $this->payingStore();
        $this->billing()->generateDueInvoices();
        $this->billing()->reviewOverdue(today()->addDays(9));

        $this->assertSame(StoreStatus::Suspended, $store->fresh()->status);

        $invoice = StoreInvoice::firstOrFail();

        $this->billing()->recordPayment($invoice, [
            'amount' => 199,
            'method' => PaymentMethodType::BankTransfer->value,
            'received_on' => today()->toDateString(),
        ], PlatformUser::factory()->create());

        $this->assertSame(StoreStatus::Active, $store->fresh()->status);

        $owner = User::factory()->superAdmin()->create();
        $this->actingAs($owner)->get('http://'.$store->host().'/dashboard')->assertOk();
    }

    public function test_the_billing_screens_are_platform_only(): void
    {
        $this->payingStore();
        $this->billing()->generateDueInvoices();
        $invoice = StoreInvoice::firstOrFail();

        $this->get($this->platformUrl('/billing'))->assertRedirect($this->platformUrl('/login'));

        $admin = PlatformUser::factory()->create();

        $this->actingAs($admin, 'platform')->get($this->platformUrl('/billing'))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee($this->store->name);

        $this->actingAs($admin, 'platform')->get($this->platformUrl('/billing/'.$invoice->number))
            ->assertOk()
            ->assertSee('Record a payment');
    }

    public function test_plans_can_be_managed_from_the_platform(): void
    {
        $admin = PlatformUser::factory()->create();

        $this->actingAs($admin, 'platform')
            ->post($this->platformUrl('/plans'), [
                'name' => 'Growth',
                'slug' => 'growth',
                'monthly_price' => 249,
                'billing_period' => BillingPeriod::Yearly->value,
                'currency_code' => 'SAR',
                'is_active' => '1',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('plans', ['slug' => 'growth', 'monthly_price' => 249]);

        $this->actingAs($admin, 'platform')->get($this->platformUrl('/plans'))->assertOk()->assertSee('Growth');
    }

    public function test_a_plan_in_use_cannot_be_deleted(): void
    {
        $store = $this->payingStore();
        $plan = Plan::findOrFail($store->plan_id);

        $this->actingAs(PlatformUser::factory()->create(), 'platform')
            ->delete($this->platformUrl('/plans/'.$plan->slug))
            ->assertSessionHas('error');

        $this->assertModelExists($plan);
    }
}
