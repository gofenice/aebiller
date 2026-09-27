<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Typing in the product search shows matches as you go, without waiting for
 * the form to be submitted.
 */
class ProductLiveFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
    }

    public function test_the_lookup_answers_with_matches_as_json(): void
    {
        $ghee = Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Milma Cow Ghee', 'selling_price' => 15]);
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Kraft Cheddar Cheese']);

        $this->actingAs($this->manager)
            ->getJson(route('products.lookup', ['q' => 'ghee']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Milma Cow Ghee')
            ->assertJsonPath('0.url', route('products.show', $ghee));
    }

    public function test_a_match_carries_what_the_dropdown_shows(): void
    {
        Product::factory()->create([
            'pack_size' => null,
            'pack_unit_id' => null,
            'name' => 'Milma Cow Ghee',
            'sku' => 'FSM-00042',
            'selling_price' => 15,
            'current_stock' => 7,
        ]);

        $row = $this->actingAs($this->manager)
            ->getJson(route('products.lookup', ['q' => 'milma']))
            ->assertOk()
            ->json('0');

        $this->assertSame('FSM-00042', $row['sku']);
        $this->assertEquals(15, $row['selling_price']);
        $this->assertEquals(7, $row['current_stock']);
        $this->assertArrayHasKey('unit', $row);
    }

    public function test_it_matches_on_sku_and_barcode_too(): void
    {
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Milma Cow Ghee', 'sku' => 'FSM-00042', 'barcode' => '6281007021234']);

        $this->actingAs($this->manager)
            ->getJson(route('products.lookup', ['q' => '6281007021234']))
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($this->manager)
            ->getJson(route('products.lookup', ['q' => 'FSM-00042']))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_the_list_filters_itself_as_the_search_is_typed(): void
    {
        $this->actingAs($this->manager)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('productFilter')
            // The block the refreshed rows are swapped into.
            ->assertSee('id="product-results"', false);
    }

    public function test_the_refreshed_list_carries_only_the_matches(): void
    {
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Milma Cow Ghee']);
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Kraft Cheddar Cheese']);

        $this->actingAs($this->manager)
            ->get(route('products.index', ['search' => 'ghee']))
            ->assertOk()
            ->assertSee('Milma Cow Ghee')
            ->assertDontSee('Kraft Cheddar Cheese');
    }

    public function test_another_store_s_products_never_appear(): void
    {
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Milma Cow Ghee']);

        $this->useStore(Store::factory()->create(['slug' => 'other']));

        $this->actingAs(User::factory()->create())
            ->getJson(route('products.lookup', ['q' => 'milma']))
            ->assertOk()
            ->assertJsonCount(0);
    }
}
