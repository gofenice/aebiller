<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function productPayload(array $overrides = []): array
    {
        $unit = Unit::factory()->create(['code' => 'pkt']);

        return [
            'name' => 'Aashirvaad Select Atta',
            'sku' => 'PKT-00099',
            'barcode' => '8901030765432',
            'type' => ProductType::Packaged->value,
            'category_id' => Category::factory()->create()->id,
            'unit_id' => $unit->id,
            'pack_size' => 5,
            'pack_unit_id' => Unit::factory()->fractional()->create(['code' => 'kg'])->id,
            'tax_rate' => 5,
            'cost_price' => 245,
            'selling_price' => 285,
            'reorder_level' => 10,
            'storage_type' => 'ambient',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_an_admin_can_add_a_packet_product(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('products.store'), $this->productPayload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'sku' => 'PKT-00099',
            'type' => ProductType::Packaged->value,
            'selling_price' => 285.00,
        ]);
    }

    public function test_opening_stock_is_posted_to_the_ledger(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('products.store'), $this->productPayload(['opening_stock' => 25]))
            ->assertSessionHasNoErrors();

        $product = Product::where('sku', 'PKT-00099')->firstOrFail();

        $this->assertSame(25.0, (float) $product->current_stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => MovementType::Opening->value,
            'direction' => 'in',
            'balance_after' => 25,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_loose_product_does_not_need_a_pack_size(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('products.store'), $this->productPayload([
                'name' => 'Tomato (Local)',
                'sku' => 'LSE-00099',
                'barcode' => null,
                'type' => ProductType::Loose->value,
                'pack_size' => null,
                'pack_unit_id' => null,
                'is_weighable' => '1',
                'min_sale_quantity' => 0.25,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'sku' => 'LSE-00099',
            'type' => ProductType::Loose->value,
            'is_weighable' => true,
            'pack_size' => null,
        ]);
    }

    public function test_a_packet_product_requires_a_pack_size(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('products.store'), $this->productPayload(['pack_size' => null]))
            ->assertSessionHasErrors('pack_size');
    }

    public function test_the_sku_must_be_unique(): void
    {
        Product::factory()->create(['sku' => 'PKT-00099']);

        $this->actingAs(User::factory()->create())
            ->post(route('products.store'), $this->productPayload())
            ->assertSessionHasErrors('sku');
    }

    public function test_editing_a_product_leaves_its_stock_alone(): void
    {
        $product = Product::factory()->create(['current_stock' => 40, 'sku' => 'PKT-00050']);

        $this->actingAs(User::factory()->create())
            ->put(route('products.update', $product), $this->productPayload([
                'sku' => 'PKT-00050',
                'barcode' => null,
                'name' => 'Renamed product',
                'opening_stock' => 999,
            ]))
            ->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertSame('Renamed product', $product->name);
        $this->assertSame(40.0, (float) $product->current_stock);
    }

    public function test_the_shelf_price_is_stored_vat_inclusive(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('products.store'), $this->productPayload([
                'selling_price' => 115,
                'tax_rate' => 15,
            ]))
            ->assertSessionHasNoErrors();

        $product = Product::where('sku', 'PKT-00099')->firstOrFail();

        $this->assertTrue($product->price_includes_tax);
        $this->assertSame(115.00, (float) $product->selling_price);
        $this->assertSame(100.00, $product->price_excluding_tax);
        $this->assertSame(115.00, $product->price_including_tax);
        $this->assertSame(15.00, $product->tax_amount);
    }

    public function test_a_net_priced_product_still_reports_correctly(): void
    {
        // Not reachable from the form, but the till and importers may set it.
        $product = Product::factory()->create([
            'selling_price' => 100,
            'tax_rate' => 15,
            'price_includes_tax' => false,
        ]);

        $this->assertSame(100.00, $product->price_excluding_tax);
        $this->assertSame(115.00, $product->price_including_tax);
        $this->assertSame(15.00, $product->tax_amount);
    }

    public function test_both_ways_of_pricing_charge_the_customer_the_same(): void
    {
        $inclusive = Product::factory()->create([
            'selling_price' => 115, 'tax_rate' => 15, 'price_includes_tax' => true, 'cost_price' => 80,
        ]);

        $exclusive = Product::factory()->create([
            'selling_price' => 100, 'tax_rate' => 15, 'price_includes_tax' => false, 'cost_price' => 80,
        ]);

        $this->assertSame($inclusive->price_including_tax, $exclusive->price_including_tax);
        $this->assertSame($inclusive->price_excluding_tax, $exclusive->price_excluding_tax);
        $this->assertSame($inclusive->margin_percent, $exclusive->margin_percent);
    }

    public function test_margin_is_measured_on_the_net_price_not_on_the_vat(): void
    {
        $product = Product::factory()->create([
            'selling_price' => 115, 'tax_rate' => 15, 'price_includes_tax' => true, 'cost_price' => 80,
        ]);

        // (100 - 80) / 80 = 25%, not (115 - 80) / 80 = 43.75%
        $this->assertSame(25.0, $product->margin_percent);
    }

    public function test_a_zero_rated_product_prices_the_same_either_way(): void
    {
        $product = Product::factory()->create([
            'selling_price' => 40, 'tax_rate' => 0, 'price_includes_tax' => false,
        ]);

        $this->assertSame(40.00, $product->price_excluding_tax);
        $this->assertSame(40.00, $product->price_including_tax);
        $this->assertSame(0.00, $product->tax_amount);
    }

    public function test_the_lookup_endpoint_finds_products_by_sku(): void
    {
        Product::factory()->create(['sku' => 'PKT-12345', 'name' => 'Tata Tea Gold']);

        $this->actingAs(User::factory()->create())
            ->getJson(route('products.lookup', ['q' => 'PKT-123']))
            ->assertOk()
            ->assertJsonFragment(['sku' => 'PKT-12345']);
    }

    public function test_a_product_can_be_switched_off_from_the_list(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->patch(route('products.toggle', $product))
            ->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->is_active);
    }
}
