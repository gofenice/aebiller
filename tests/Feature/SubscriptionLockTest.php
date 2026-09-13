<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\PaymentMethodType;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreInvoice;
use App\Models\User;
use App\Services\BillingCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A store that has not paid can do nothing but pay.
 */
class SubscriptionLockTest extends TestCase
{
    use RefreshDatabase;

    protected function billing(): BillingCycle
    {
        return app(BillingCycle::class);
    }

    /**
     * A store with one invoice that fell due, and is past its grace period.
     */
    protected function lockedStore(): StoreInvoice
    {
        $this->payingStore();
        $this->billing()->generateDueInvoices();
        $this->billing()->reviewOverdue(today()->addDays(9));

        $this->store->refresh();

        return StoreInvoice::firstOrFail();
    }

    protected function payingStore(array $overrides = []): Store
    {
        $plan = Plan::factory()->create(['monthly_price' => 199, 'billing_period' => BillingPeriod::Monthly]);

        $this->store->update([
            'plan_id' => $plan->id,
            'billing_day' => 1,
            'grace_days' => 7,
            'invoice_lead_days' => 7,
            'auto_suspend' => true,
            'prorate_first_invoice' => false,
            'next_invoice_on' => today(),
            ...$overrides,
        ]);

        return $this->store->fresh();
    }

    public function test_an_unpaid_shop_cannot_do_anything_but_pay(): void
    {
        $this->lockedStore();
        $owner = User::factory()->superAdmin()->create();

        // Every working screen is turned away.
        foreach (['products.index', 'billing.create', 'dashboard', 'sales.index'] as $route) {
            $this->actingAs($owner)->get(route($route))->assertRedirect(route('subscription.show'));
        }

        // …but the payment screen itself opens, showing what is owed.
        $this->actingAs($owner)->get(route('subscription.show'))
            ->assertOk()
            ->assertSee('The shop is locked until this is paid')
            ->assertSee('199.00');
    }

    public function test_a_till_request_that_expects_json_is_refused_rather_than_redirected(): void
    {
        $this->lockedStore();

        $this->actingAs(User::factory()->create())
            ->getJson(route('billing.scan', ['code' => '123']))
            ->assertStatus(402);
    }

    public function test_a_shopper_can_still_open_their_own_receipt(): void
    {
        $invoice = $this->lockedStore();
        $sale = Sale::factory()->create();

        $this->get(route('bill.show', $sale->uuid))->assertOk();
        $this->assertNotNull($invoice);
    }

    public function test_paying_reopens_the_shop_at_once(): void
    {
        $invoice = $this->lockedStore();
        $owner = User::factory()->superAdmin()->create();

        $this->billing()->recordPayment($invoice, [
            'amount' => 199,
            'method' => PaymentMethodType::BankTransfer->value,
            'received_on' => today()->toDateString(),
        ]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    }

    public function test_a_shop_inside_its_grace_period_keeps_working_but_is_warned(): void
    {
        $this->payingStore();
        $this->billing()->generateDueInvoices();
        $this->billing()->reviewOverdue(today()->addDay());

        $this->store->refresh();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Subscription overdue')
            ->assertSee('Pay now');
    }

    public function test_an_invoice_raised_early_is_shown_as_due_soon_and_locks_nothing(): void
    {
        // Renewal is in five days, inside the seven-day notice window.
        $this->payingStore(['next_invoice_on' => today()->addDays(5)]);

        $this->assertSame(1, $this->billing()->generateDueInvoices()['invoices']);

        $invoice = StoreInvoice::firstOrFail();
        $this->assertTrue($invoice->due_on->isSameDay(today()->addDays(5)), 'due on the renewal date, not today');
        $this->assertTrue($invoice->isDueSoon());

        $this->store->refresh();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Subscription due soon');
    }

    public function test_a_cashier_is_locked_out_but_not_asked_to_pay(): void
    {
        $this->lockedStore();

        $this->actingAs(User::factory()->create())
            ->get(route('subscription.show'))
            ->assertOk()
            ->assertSee("Only the shop owner's account can pay this", false)
            ->assertDontSee('Pay 199');
    }

    public function test_a_store_that_is_only_warned_is_never_locked(): void
    {
        $this->payingStore(['auto_suspend' => false]);
        $this->billing()->generateDueInvoices();
        $this->billing()->reviewOverdue(today()->addDays(60));

        $this->store->refresh();

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('dashboard'))->assertOk();
    }
}
