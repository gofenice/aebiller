<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\Store;
use App\Models\StoreInvoice;
use App\Services\BillingCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A shop joining part-way through a month pays only for the days it uses.
 */
class ProRataBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // A 30-day month keeps the arithmetic in these tests obvious.
        $this->travelTo('2026-09-11 09:00');
    }

    protected function billing(): BillingCycle
    {
        return app(BillingCycle::class);
    }

    protected function storeJoining(string $on, array $overrides = []): Store
    {
        $plan = Plan::factory()->create([
            'monthly_price' => 300,
            'billing_period' => $overrides['period'] ?? BillingPeriod::Monthly,
            'currency_code' => 'SAR',
        ]);

        $this->store->update([
            'plan_id' => $plan->id,
            'billing_day' => 1,
            'invoice_lead_days' => 7,
            'prorate_first_invoice' => true,
            'billing_starts_on' => $on,
            'next_invoice_on' => $on,
            ...collect($overrides)->except('period')->all(),
        ]);

        return $this->store->fresh();
    }

    public function test_a_shop_joining_mid_month_pays_only_for_the_days_left(): void
    {
        $store = $this->storeJoining('2026-09-11');

        $this->billing()->generateDueInvoices();

        $invoice = StoreInvoice::firstOrFail();

        // 11 Sep to 1 Oct is 20 of September's 30 days: 300 × 20/30.
        $this->assertSame(200.00, (float) $invoice->amount);
        $this->assertTrue($invoice->is_prorated);
        $this->assertTrue($invoice->period_end->isSameDay('2026-09-30'));
        $this->assertStringContainsString('20 of 30 days', $invoice->notes);

        // …and the next one lands on the billing day.
        $this->assertTrue($store->fresh()->next_invoice_on->isSameDay('2026-10-01'));
    }

    public function test_the_month_after_is_charged_in_full(): void
    {
        $this->storeJoining('2026-09-11');
        $this->billing()->generateDueInvoices();

        // The October invoice is raised seven days early, on 24 September.
        $this->travelTo('2026-09-24 01:00');
        $this->billing()->generateDueInvoices();

        $second = StoreInvoice::orderByDesc('id')->first();

        $this->assertSame(300.00, (float) $second->amount);
        $this->assertFalse($second->is_prorated);
        $this->assertTrue($second->due_on->isSameDay('2026-10-01'));
        $this->assertDatabaseCount('store_invoices', 2);
    }

    public function test_a_shop_joining_on_the_billing_day_pays_the_full_amount(): void
    {
        $this->storeJoining('2026-09-01');

        $this->billing()->generateDueInvoices();

        $invoice = StoreInvoice::firstOrFail();

        $this->assertSame(300.00, (float) $invoice->amount);
        $this->assertFalse($invoice->is_prorated);
    }

    public function test_pro_rata_can_be_switched_off_for_a_store(): void
    {
        $this->storeJoining('2026-09-11', ['prorate_first_invoice' => false]);

        $this->billing()->generateDueInvoices();

        $this->assertSame(300.00, (float) StoreInvoice::firstOrFail()->amount);
    }

    public function test_a_yearly_store_starts_its_year_on_the_day_it_joins(): void
    {
        $this->storeJoining('2026-09-11', ['period' => BillingPeriod::Yearly]);

        $this->billing()->generateDueInvoices();

        $invoice = StoreInvoice::firstOrFail();

        $this->assertSame(300.00, (float) $invoice->amount, 'a year has no part period to align to');
        $this->assertFalse($invoice->is_prorated);
        $this->assertTrue($invoice->period_end->isSameDay('2027-09-10'));
    }

    public function test_a_lifetime_store_is_billed_once_and_never_again(): void
    {
        $store = $this->storeJoining('2026-09-11', ['period' => BillingPeriod::Lifetime]);

        $this->billing()->generateDueInvoices();

        $invoice = StoreInvoice::firstOrFail();

        $this->assertSame(300.00, (float) $invoice->amount);
        $this->assertNull($invoice->period_end);
        $this->assertSame('Lifetime', $invoice->periodLabel());
        $this->assertNull($store->fresh()->next_invoice_on);

        // A year later there is still nothing to raise.
        $this->travelTo('2027-10-01 01:00');
        $this->assertSame(0, $this->billing()->generateDueInvoices()['invoices']);
        $this->assertDatabaseCount('store_invoices', 1);
    }
}
