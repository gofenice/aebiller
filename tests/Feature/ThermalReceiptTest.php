<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bill as the 80mm roll wants it: its own bare page, sized to the paper.
 */
class ThermalReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create();
    }

    protected function bill(): Sale
    {
        $product = Product::factory()->create([
            'name' => 'Almarai Cream Cheese 500 g',
            'current_stock' => 50,
            'selling_price' => 11.50,
            'tax_rate' => 15,
            'price_includes_tax' => true,
        ]);

        $this->actingAs($this->cashier)->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Cash->value,
            'amount_paid' => 50,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        return Sale::latest('id')->firstOrFail();
    }

    public function test_the_page_is_sized_for_the_roll_and_prints_itself(): void
    {
        $response = $this->actingAs($this->cashier)
            ->get(route('sales.print', $this->bill()))
            ->assertOk();

        // 80mm paper, 72mm of print, and no page margin of the browser's own.
        $response->assertSee('size: 80mm auto', false);
        $response->assertSee('margin: 0', false);
        $response->assertSee('width: 72mm', false);
        $response->assertSee('window.print()', false);
    }

    public function test_it_carries_the_figures_the_customer_checks(): void
    {
        $sale = $this->bill();

        $this->actingAs($this->cashier)
            ->get(route('sales.print', $sale))
            ->assertOk()
            ->assertSee($sale->invoice_no)
            ->assertSee('Almarai Cream Cheese 500 g')
            ->assertSee('NET PAYABLE')
            ->assertSee(number_format((float) $sale->grand_total, 2))
            ->assertSee(number_format((float) $sale->vat_total, 2))
            ->assertSee('Simplified Tax Invoice', false);
    }

    public function test_it_is_a_bare_page_without_the_app_shell(): void
    {
        $response = $this->actingAs($this->cashier)
            ->get(route('sales.print', $this->bill()))
            ->assertOk();

        // None of the app's styling: that is what printed as a grey wash.
        $response->assertDontSee('build/assets/app', false);
        $response->assertDontSee('Stock Ledger');
    }

    public function test_a_voided_bill_says_so_on_the_paper(): void
    {
        $sale = $this->bill();
        $sale->update(['status' => SaleStatus::Voided]);

        $this->actingAs($this->cashier)
            ->get(route('sales.print', $sale))
            ->assertOk()
            ->assertSee('VOIDED');
    }

    public function test_it_needs_a_signed_in_till_user(): void
    {
        $sale = $this->bill();

        auth()->logout();

        $this->get(route('sales.print', $sale))->assertRedirectContains('/login');
    }

    public function test_another_store_cannot_print_this_bill(): void
    {
        $sale = $this->bill();

        $this->useStore(Store::factory()->create(['slug' => 'other']));

        $this->actingAs(User::factory()->create())
            ->get(route('sales.print', $sale))
            ->assertNotFound();
    }
}
