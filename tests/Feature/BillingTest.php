<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Shelf prices include VAT, which is how the store actually prices.
     */
    protected function sellableProduct(array $overrides = []): Product
    {
        return Product::factory()->create([
            'current_stock' => 50,
            'selling_price' => 11.50,
            'cost_price' => 8.00,
            'tax_rate' => 15,
            'price_includes_tax' => true,
            ...$overrides,
        ]);
    }

    public function test_a_scanned_barcode_returns_the_product_to_the_till(): void
    {
        $product = $this->sellableProduct(['barcode' => '6281007021234']);

        $this->actingAs(User::factory()->create())
            ->getJson(route('billing.scan', ['code' => '6281007021234']))
            ->assertOk()
            ->assertJsonPath('match.id', $product->id)
            ->assertJsonPath('match.unit_price', 11.5);
    }

    public function test_a_typed_product_code_resolves_the_same_way(): void
    {
        $product = $this->sellableProduct(['sku' => 'PKT-04242']);

        $this->actingAs(User::factory()->create())
            ->getJson(route('billing.scan', ['code' => 'PKT-04242']))
            ->assertOk()
            ->assertJsonPath('match.id', $product->id);
    }

    public function test_a_partial_name_returns_a_list_to_choose_from(): void
    {
        $this->sellableProduct(['name' => 'Tomato Local', 'sku' => 'LSE-00001', 'barcode' => null]);
        $this->sellableProduct(['name' => 'Tomato Cherry', 'sku' => 'LSE-00002', 'barcode' => null]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('billing.scan', ['code' => 'Tomato']))
            ->assertOk()
            ->assertJsonPath('match', null)
            ->assertJsonCount(2, 'results');
    }

    public function test_the_suggestion_list_leads_with_names_that_start_with_the_term(): void
    {
        $this->sellableProduct(['name' => 'Tide Automatic Detergent', 'sku' => 'PKT-00100', 'barcode' => null]);
        $this->sellableProduct(['name' => 'Tomato Local', 'sku' => 'LSE-00100', 'barcode' => null]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('billing.scan', ['code' => 'toma']))
            ->assertOk()
            ->assertJsonPath('results.0.sku', 'LSE-00100')
            ->assertJsonPath('results.1.sku', 'PKT-00100');
    }

    public function test_an_exact_code_is_offered_first_and_still_listed_with_the_rest(): void
    {
        $exact = $this->sellableProduct(['name' => 'Zebra Water', 'sku' => 'PKT-00500', 'barcode' => '6281999000011']);
        $this->sellableProduct(['name' => 'PKT-00500 Lookalike', 'sku' => 'PKT-00501', 'barcode' => null]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('billing.scan', ['code' => 'PKT-00500']))
            ->assertOk()
            ->assertJsonPath('match.id', $exact->id)
            ->assertJsonPath('results.0.id', $exact->id)
            ->assertJsonCount(2, 'results');
    }

    public function test_a_single_character_is_too_short_to_search(): void
    {
        $this->sellableProduct(['name' => 'Tomato Local']);

        $this->actingAs(User::factory()->create())
            ->getJson(route('billing.scan', ['code' => '']))
            ->assertOk()
            ->assertJsonPath('match', null)
            ->assertJsonCount(0, 'results');
    }

    public function test_a_cash_sale_prices_vat_out_of_the_shelf_price(): void
    {
        $cashier = User::factory()->create();
        $product = $this->sellableProduct();

        $this->actingAs($cashier)->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Cash->value,
            'amount_paid' => 50,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
            ],
        ])->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();

        // 4 x 11.50 = 46.00 gross, VAT = 46 x 15/115 = 6.00, net = 40.00
        $this->assertSame(46.00, (float) $sale->grand_total);
        $this->assertSame(6.00, (float) $sale->vat_total);
        $this->assertSame(40.00, (float) $sale->subtotal_excl_vat);
        $this->assertSame(4.00, (float) $sale->change_due);
        $this->assertSame($cashier->id, $sale->cashier_id);
    }

    public function test_completing_a_sale_takes_the_goods_out_of_stock(): void
    {
        $product = $this->sellableProduct(['current_stock' => 20]);

        $this->actingAs(User::factory()->create())->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Card->value,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(17.0, (float) $product->fresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => MovementType::Sale->value,
            'direction' => 'out',
            'quantity' => 3,
            'balance_after' => 17,
        ]);
    }

    public function test_a_weighed_item_can_be_billed_in_fractions(): void
    {
        $product = $this->sellableProduct([
            'current_stock' => 40,
            'selling_price' => 5.50,
            'is_weighable' => true,
        ]);

        $this->actingAs(User::factory()->create())->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Cash->value,
            'amount_paid' => 20,
            'items' => [['product_id' => $product->id, 'quantity' => 1.35]],
        ])->assertSessionHasNoErrors();

        // 1.35 kg x 5.50 = 7.425, rounded to 7.43
        $this->assertSame(7.43, (float) Sale::firstOrFail()->grand_total);
        $this->assertSame(38.65, (float) $product->fresh()->current_stock);
    }

    public function test_a_bill_discount_is_spread_across_the_lines(): void
    {
        $first = $this->sellableProduct(['selling_price' => 60]);
        $second = $this->sellableProduct(['selling_price' => 40]);

        $this->actingAs(User::factory()->create())->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Card->value,
            'bill_discount' => 10,
            'items' => [
                ['product_id' => $first->id, 'quantity' => 1],
                ['product_id' => $second->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $sale = Sale::with('items')->firstOrFail();

        $this->assertSame(90.00, (float) $sale->grand_total);
        $this->assertSame(10.00, (float) $sale->bill_discount);
        // The discount lands 6 / 4 in line with each line's share of the basket.
        $this->assertSame([54.00, 36.00], $sale->items->pluck('line_total')->map(fn ($v) => (float) $v)->all());
        $this->assertSame(90.00, round((float) $sale->subtotal_excl_vat + (float) $sale->vat_total, 2));
    }

    public function test_a_line_discount_reduces_that_line_only(): void
    {
        $product = $this->sellableProduct(['selling_price' => 100]);

        $this->actingAs(User::factory()->create())->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Card->value,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'discount_percent' => 25]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(75.00, (float) Sale::firstOrFail()->grand_total);
    }

    public function test_a_sale_cannot_exceed_the_stock_on_hand(): void
    {
        $product = $this->sellableProduct(['current_stock' => 2]);

        $this->actingAs(User::factory()->create())->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Cash->value,
            'amount_paid' => 500,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(2.0, (float) $product->fresh()->current_stock);
    }

    public function test_an_inactive_product_cannot_be_sold(): void
    {
        $product = $this->sellableProduct(['is_active' => false]);

        $this->actingAs(User::factory()->create())->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Card->value,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_short_cash_is_refused(): void
    {
        $product = $this->sellableProduct(['selling_price' => 100]);

        $this->actingAs(User::factory()->create())->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Cash->value,
            'amount_paid' => 40,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_invoice_numbers_run_in_sequence(): void
    {
        $cashier = User::factory()->create();
        $product = $this->sellableProduct(['current_stock' => 100]);

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($cashier)->post(route('billing.store'), [
                'payment_method' => PaymentMethod::Card->value,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])->assertSessionHasNoErrors();
        }

        $period = now()->format('ym');

        $this->assertSame(
            ["INV-{$period}-0001", "INV-{$period}-0002"],
            Sale::orderBy('id')->pluck('invoice_no')->all(),
        );
    }

    public function test_the_bill_is_readable_online_without_signing_in(): void
    {
        $product = $this->sellableProduct();

        $this->actingAs(User::factory()->create())->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Card->value,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();

        $this->post(route('logout'));

        $this->get(route('bill.show', $sale->uuid))
            ->assertOk()
            ->assertSee($sale->invoice_no)
            ->assertSee($product->name);
    }

    public function test_the_receipt_carries_a_qr_code_for_the_online_bill(): void
    {
        $product = $this->sellableProduct();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Card->value,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();

        $this->actingAs($user)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee(route('bill.show', $sale->uuid));
    }

    public function test_only_a_super_admin_can_void_a_bill(): void
    {
        $product = $this->sellableProduct(['current_stock' => 10]);
        $cashier = User::factory()->create();

        $this->actingAs($cashier)->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Card->value,
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();
        $this->assertSame(6.0, (float) $product->fresh()->current_stock);

        $this->actingAs($cashier)->delete(route('sales.destroy', $sale))->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('sales.destroy', $sale), ['void_reason' => 'Wrong item scanned'])
            ->assertSessionHasNoErrors();

        $sale->refresh();

        $this->assertSame(SaleStatus::Voided, $sale->status);
        $this->assertSame(10.0, (float) $product->fresh()->current_stock, 'voiding puts the goods back');
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => MovementType::SalesReturn->value,
            'direction' => 'in',
        ]);
    }

    public function test_a_bill_cannot_be_voided_twice(): void
    {
        $product = $this->sellableProduct();
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($owner)->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Card->value,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();

        $this->actingAs($owner)->delete(route('sales.destroy', $sale))->assertSessionHasNoErrors();
        $this->actingAs($owner)->delete(route('sales.destroy', $sale))->assertSessionHas('error');

        $this->assertSame(1, $product->fresh()->movements()->where('type', MovementType::SalesReturn)->count());
    }

    public function test_an_empty_basket_is_refused(): void
    {
        $this->actingAs(User::factory()->create())->post(route('billing.store'), [
            'payment_method' => PaymentMethod::Cash->value,
            'items' => [],
        ])->assertSessionHasErrors('items');
    }
}
