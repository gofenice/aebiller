<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The register wrote the pack size into the product's name. This puts it
 * where the form expects it.
 */
class PackSizeSplitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['Gram', 'g'], ['Kilogram', 'kg'], ['Millilitre', 'ml'], ['Litre', 'ltr'], ['Piece', 'pc']] as [$name, $code]) {
            Unit::factory()->create(['name' => $name, 'code' => $code]);
        }
    }

    protected function split(): void
    {
        $this->artisan('products:split-pack-size', ['--store' => $this->store->slug])->assertSuccessful();
    }

    public function test_a_millilitre_size_moves_out_of_the_name(): void
    {
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Milma Cow Ghee 500 ml']);

        $this->split();

        $product = Product::sole();

        $this->assertSame('Milma Cow Ghee', $product->name);
        $this->assertSame('500.000', $product->pack_size);
        $this->assertSame('ml', $product->packUnit->code);
    }

    public function test_grams_kilos_and_litres_are_read_the_same_way(): void
    {
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Almarai Cream Cheese 500 g']);
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Al Osra Sugar 5 kg']);
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Almarai Laban Full Fat 1.5 L']);

        $this->split();

        $this->assertSame('g', Product::where('name', 'Almarai Cream Cheese')->sole()->packUnit->code);
        $this->assertSame('kg', Product::where('name', 'Al Osra Sugar')->sole()->packUnit->code);

        $laban = Product::where('name', 'Almarai Laban Full Fat')->sole();
        $this->assertSame('1.500', $laban->pack_size);
        $this->assertSame('ltr', $laban->packUnit->code);
    }

    public function test_a_name_without_a_size_is_left_alone(): void
    {
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Kraft Cheddar Cheese']);

        $this->split();

        $product = Product::sole();

        $this->assertSame('Kraft Cheddar Cheese', $product->name);
        $this->assertNull($product->pack_size);
    }

    public function test_a_count_of_pieces_stays_in_the_name(): void
    {
        // "30 pads" says what is in the box, not how much it holds.
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Kotex Pads 30s 30 pads']);
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Lipton Yellow Tea Bags 100s 100 tea bags']);

        $this->split();

        $this->assertSame(2, Product::whereNull('pack_size')->count());
    }

    public function test_it_does_not_strip_a_number_that_is_part_of_the_name(): void
    {
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Pepsi Diet']);
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Indomie Special Chicken Noodles']);

        $this->split();

        $this->assertSame(2, Product::whereNull('pack_size')->count());
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        Product::factory()->create(['pack_size' => null, 'pack_unit_id' => null, 'name' => 'Milma Cow Ghee 500 ml']);

        $this->artisan('products:split-pack-size', ['--store' => $this->store->slug, '--dry-run' => true])
            ->expectsOutputToContain('Dry run');

        $this->assertSame('Milma Cow Ghee 500 ml', Product::sole()->name);
    }
}
