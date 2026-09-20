<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\LoyaltyTier;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\BillingService;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Bills closed on credit: the goods leave, the money is still owed, and the
 * balance is collected later in whole or in part.
 */
class CreditSaleTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create();

        LoyaltyTier::factory()->create(['name' => 'Classic', 'min_spend' => 0, 'earn_multiplier' => 1]);
    }

    protected function sellableProduct(): Product
    {
        return Product::factory()->create([
            'current_stock' => 50,
            'selling_price' => 11.50,
            'cost_price' => 8.00,
            'tax_rate' => 15,
            'price_includes_tax' => true,
        ]);
    }

    protected function member(): Customer
    {
        return app(LoyaltyService::class)
            ->enrol(['name' => 'Aisha Rahman', 'phone' => '0551234567'], $this->cashier)
            ->fresh(['activeCard', 'tier']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function sellOnCredit(Product $product, float $quantity, array $attributes = []): TestResponse
    {
        return $this->actingAs($this->cashier)->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Credit->value,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            ...$attributes,
        ]);
    }

    public function test_a_credit_bill_completes_with_the_whole_total_still_owed(): void
    {
        $product = $this->sellableProduct();
        $customer = $this->member();

        $this->sellOnCredit($product, 2, ['customer_id' => $customer->id]);

        $sale = Sale::latest('id')->firstOrFail();

        $this->assertTrue($sale->isCredit());
        $this->assertSame('23.00', $sale->grand_total);
        $this->assertSame('0.00', $sale->amount_paid);
        $this->assertSame('23.00', $sale->amount_outstanding);
        $this->assertSame('0.00', $sale->change_due);
        $this->assertNull($sale->settled_at);
        $this->assertFalse($sale->isSettled());

        // The sale is complete, so the goods have left the shelf.
        $this->assertSame(48.0, (float) $product->fresh()->current_stock);
    }

    public function test_a_part_payment_at_the_till_leaves_the_rest_on_account(): void
    {
        $product = $this->sellableProduct();
        $customer = $this->member();

        $this->sellOnCredit($product, 2, ['customer_id' => $customer->id, 'amount_paid' => 10]);

        $sale = Sale::latest('id')->firstOrFail();

        $this->assertSame('10.00', $sale->amount_paid);
        $this->assertSame('13.00', $sale->amount_outstanding);
    }

    public function test_credit_is_refused_without_a_member_to_chase(): void
    {
        $product = $this->sellableProduct();

        $this->sellOnCredit($product, 1)->assertSessionHasErrors('customer_id');

        $this->assertSame(0, Sale::count());
        $this->assertSame(50.0, (float) $product->fresh()->current_stock);
    }

    public function test_a_cash_bill_owes_nothing(): void
    {
        $product = $this->sellableProduct();

        $this->actingAs($this->cashier)->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Cash->value,
            'amount_paid' => 50,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $sale = Sale::latest('id')->firstOrFail();

        $this->assertSame('0.00', $sale->amount_outstanding);
        $this->assertSame('27.00', $sale->change_due);
    }

    public function test_a_part_payment_lowers_the_balance_and_is_recorded(): void
    {
        $product = $this->sellableProduct();
        $customer = $this->member();
        $this->sellOnCredit($product, 2, ['customer_id' => $customer->id]);
        $sale = Sale::latest('id')->firstOrFail();

        $this->actingAs($this->cashier)
            ->post(route('sales.credit-payments.store', $sale), [
                'amount' => 8,
                'payment_method' => PaymentMethod::Cash->value,
                'reference' => 'Receipt 12',
            ])
            ->assertSessionHasNoErrors();

        $sale->refresh();

        $this->assertSame('15.00', $sale->amount_outstanding);
        $this->assertSame('8.00', $sale->amount_paid);
        $this->assertNull($sale->settled_at);

        $payment = $sale->creditPayments()->sole();
        $this->assertSame('8.00', $payment->amount);
        $this->assertSame('Receipt 12', $payment->reference);
        $this->assertSame($customer->id, $payment->customer_id);
        $this->assertSame($this->cashier->id, $payment->received_by);
    }

    public function test_paying_the_balance_settles_the_bill(): void
    {
        $product = $this->sellableProduct();
        $customer = $this->member();
        $this->sellOnCredit($product, 2, ['customer_id' => $customer->id]);
        $sale = Sale::latest('id')->firstOrFail();

        $this->actingAs($this->cashier)->post(route('sales.credit-payments.store', $sale), [
            'amount' => 23,
            'payment_method' => PaymentMethod::Transfer->value,
        ]);

        $sale->refresh();

        $this->assertSame('0.00', $sale->amount_outstanding);
        $this->assertTrue($sale->isSettled());
        $this->assertNotNull($sale->settled_at);
        $this->assertSame(0, Sale::outstanding()->count());
    }

    public function test_more_than_the_balance_is_refused(): void
    {
        $product = $this->sellableProduct();
        $customer = $this->member();
        $this->sellOnCredit($product, 2, ['customer_id' => $customer->id]);
        $sale = Sale::latest('id')->firstOrFail();

        $this->actingAs($this->cashier)
            ->post(route('sales.credit-payments.store', $sale), [
                'amount' => 100,
                'payment_method' => PaymentMethod::Cash->value,
            ])
            ->assertSessionHas('error');

        $this->assertSame('23.00', $sale->fresh()->amount_outstanding);
        $this->assertSame(0, $sale->creditPayments()->count());
    }

    public function test_credit_cannot_pay_off_credit(): void
    {
        $product = $this->sellableProduct();
        $customer = $this->member();
        $this->sellOnCredit($product, 2, ['customer_id' => $customer->id]);
        $sale = Sale::latest('id')->firstOrFail();

        $this->actingAs($this->cashier)
            ->post(route('sales.credit-payments.store', $sale), [
                'amount' => 5,
                'payment_method' => PaymentMethod::Credit->value,
            ])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_a_settled_bill_takes_no_more_money(): void
    {
        $product = $this->sellableProduct();
        $customer = $this->member();
        $this->sellOnCredit($product, 2, ['customer_id' => $customer->id]);
        $sale = Sale::latest('id')->firstOrFail();

        app(BillingService::class)->settleCredit($sale, ['amount' => 23], $this->cashier);

        $this->expectException(RuntimeException::class);

        app(BillingService::class)->settleCredit($sale->fresh(), ['amount' => 1], $this->cashier);
    }

    public function test_voiding_a_credit_bill_writes_off_what_was_owed(): void
    {
        $product = $this->sellableProduct();
        $customer = $this->member();
        $this->sellOnCredit($product, 2, ['customer_id' => $customer->id]);
        $sale = Sale::latest('id')->firstOrFail();

        app(BillingService::class)->voidSale($sale, User::factory()->superAdmin()->create(), 'Wrong item');

        $sale->refresh();

        $this->assertSame('0.00', $sale->amount_outstanding);
        $this->assertSame(0, Sale::outstanding()->count());
    }

    public function test_the_report_lists_what_is_still_owed(): void
    {
        $product = $this->sellableProduct();
        $customer = $this->member();
        $this->sellOnCredit($product, 2, ['customer_id' => $customer->id]);
        $sale = Sale::latest('id')->firstOrFail();

        $this->actingAs($this->cashier)
            ->get(route('reports.credit'))
            ->assertOk()
            ->assertSee($sale->invoice_no)
            ->assertSee('Aisha Rahman')
            ->assertSee('23.00');
    }

    public function test_a_settled_bill_drops_off_the_report(): void
    {
        $product = $this->sellableProduct();
        $customer = $this->member();
        $this->sellOnCredit($product, 2, ['customer_id' => $customer->id]);
        $sale = Sale::latest('id')->firstOrFail();

        app(BillingService::class)->settleCredit($sale, ['amount' => 23], $this->cashier);

        // The "bill completed" flash still holds the number, so it has to go
        // before the page can be checked for it.
        $this->flushSession();

        $this->actingAs($this->cashier)
            ->get(route('reports.credit'))
            ->assertOk()
            ->assertDontSee($sale->invoice_no)
            ->assertSee('Nothing outstanding');
    }

    public function test_another_store_never_sees_this_store_s_debts(): void
    {
        $product = $this->sellableProduct();
        $customer = $this->member();
        $this->sellOnCredit($product, 2, ['customer_id' => $customer->id]);
        $sale = Sale::latest('id')->firstOrFail();

        $this->useStore(Store::factory()->create(['slug' => 'other']));

        $this->assertSame(0, Sale::outstanding()->count());
        $this->assertSame(0, CreditPayment::count());

        $this->actingAs(User::factory()->create())
            ->get(route('reports.credit'))
            ->assertOk()
            ->assertSee('Nothing outstanding');

        $this->assertTrue($sale->exists);
    }
}
