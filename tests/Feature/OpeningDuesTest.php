<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Money customers already owed the shop before it started billing here, and
 * collecting it alongside the credit bills raised since.
 */
class OpeningDuesTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->superAdmin()->create();
    }

    protected function member(string $name = 'Aisha Rahman'): Customer
    {
        return Customer::factory()->create(['name' => $name, 'phone' => '0551234567']);
    }

    public function test_a_balance_from_the_old_book_goes_on_the_customer(): void
    {
        $customer = $this->member();

        $this->actingAs($this->manager)
            ->post(route('customers.opening-due.store', $customer), [
                'amount' => 450.75,
                'opening_due_on' => '2026-08-31',
                'opening_due_note' => 'From the old ledger',
            ])
            ->assertSessionHasNoErrors();

        $customer->refresh();

        $this->assertSame('450.75', $customer->opening_due);
        $this->assertSame('450.75', $customer->opening_due_outstanding);
        $this->assertSame('2026-08-31', $customer->opening_due_on->toDateString());
        $this->assertSame('From the old ledger', $customer->opening_due_note);
        $this->assertSame(450.75, $customer->totalDue());
    }

    public function test_part_of_the_old_balance_can_be_paid_off(): void
    {
        $customer = $this->member();
        app(BillingService::class)->setOpeningDue($customer, ['amount' => 450]);

        $this->actingAs($this->manager)
            ->post(route('customers.opening-due.pay', $customer), [
                'amount' => 200,
                'payment_method' => PaymentMethod::Cash->value,
                'reference' => 'Receipt 8',
            ])
            ->assertSessionHasNoErrors();

        $customer->refresh();

        $this->assertSame('250.00', $customer->opening_due_outstanding);
        // The original figure stays, so the shop can still see what it was.
        $this->assertSame('450.00', $customer->opening_due);

        $payment = CreditPayment::sole();
        $this->assertNull($payment->sale_id);
        $this->assertSame($customer->id, $payment->customer_id);
        $this->assertSame('200.00', $payment->amount);
        $this->assertSame('Receipt 8', $payment->reference);
    }

    public function test_paying_it_all_off_clears_the_balance(): void
    {
        $customer = $this->member();
        app(BillingService::class)->setOpeningDue($customer, ['amount' => 120]);

        app(BillingService::class)->settleOpeningDue($customer, ['amount' => 120], $this->manager);

        $this->assertSame(0.0, $customer->fresh()->totalDue());
    }

    public function test_more_than_the_balance_is_refused(): void
    {
        $customer = $this->member();
        app(BillingService::class)->setOpeningDue($customer, ['amount' => 100]);

        $this->actingAs($this->manager)
            ->post(route('customers.opening-due.pay', $customer), [
                'amount' => 150,
                'payment_method' => PaymentMethod::Cash->value,
            ])
            ->assertSessionHas('error');

        $this->assertSame('100.00', $customer->fresh()->opening_due_outstanding);
    }

    public function test_correcting_the_total_keeps_what_was_already_paid(): void
    {
        $customer = $this->member();
        $billing = app(BillingService::class);

        $billing->setOpeningDue($customer, ['amount' => 500]);
        $billing->settleOpeningDue($customer, ['amount' => 200], $this->manager);

        // The shop finds the real figure was 600, not 500.
        $billing->setOpeningDue($customer->fresh(), ['amount' => 600]);

        $customer->refresh();

        $this->assertSame('600.00', $customer->opening_due);
        $this->assertSame('400.00', $customer->opening_due_outstanding);
    }

    public function test_it_cannot_be_set_below_what_has_been_collected(): void
    {
        $customer = $this->member();
        $billing = app(BillingService::class);

        $billing->setOpeningDue($customer, ['amount' => 500]);
        $billing->settleOpeningDue($customer, ['amount' => 200], $this->manager);

        $this->expectException(RuntimeException::class);

        $billing->setOpeningDue($customer->fresh(), ['amount' => 150]);
    }

    public function test_the_old_balance_and_the_credit_bills_add_up(): void
    {
        $customer = $this->member();
        app(BillingService::class)->setOpeningDue($customer, ['amount' => 300]);

        Sale::factory()->credit()->create([
            'customer_id' => $customer->id,
            'grand_total' => 120,
            'amount_outstanding' => 120,
        ]);

        $this->assertSame(120.0, $customer->billsDue());
        $this->assertSame(420.0, $customer->fresh()->totalDue());
    }

    public function test_the_credit_report_counts_the_old_balances(): void
    {
        $customer = $this->member();
        app(BillingService::class)->setOpeningDue($customer, [
            'amount' => 300,
            'opening_due_note' => 'From the old ledger',
        ]);

        $this->actingAs($this->manager)
            ->get(route('reports.credit'))
            ->assertOk()
            ->assertSee('Carried over from before')
            ->assertSee('Aisha Rahman')
            ->assertSee('From the old ledger')
            ->assertSee('300.00');
    }

    public function test_the_member_list_shows_what_is_owed_and_can_filter_to_it(): void
    {
        $owing = $this->member('Aisha Rahman');
        app(BillingService::class)->setOpeningDue($owing, ['amount' => 300]);

        Customer::factory()->create(['name' => 'Clear Customer', 'phone' => '0559999999']);

        $this->actingAs($this->manager)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('300.00')
            ->assertSee('Clear Customer');

        $this->actingAs($this->manager)
            ->get(route('customers.index', ['status' => 'owing']))
            ->assertOk()
            ->assertSee('Aisha Rahman')
            ->assertDontSee('Clear Customer');
    }

    public function test_the_list_counts_unpaid_bills_towards_what_a_member_owes(): void
    {
        $customer = $this->member();

        Sale::factory()->credit()->create([
            'customer_id' => $customer->id,
            'grand_total' => 75.50,
            'amount_outstanding' => 75.50,
        ]);

        $this->actingAs($this->manager)
            ->get(route('customers.index', ['status' => 'owing']))
            ->assertOk()
            ->assertSee('75.50');
    }

    public function test_another_store_never_sees_these_balances(): void
    {
        $customer = $this->member();
        app(BillingService::class)->setOpeningDue($customer, ['amount' => 300]);

        $this->useStore(Store::factory()->create(['slug' => 'other']));

        $this->assertSame(0, Customer::owing()->count());
        $this->assertSame(0, CreditPayment::count());
    }
}
