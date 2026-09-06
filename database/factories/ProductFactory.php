<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\StorageType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 10, 500);

        return [
            'sku' => 'PKT-'.fake()->unique()->numerify('#####'),
            'barcode' => fake()->unique()->ean13(),
            'name' => ucfirst(fake()->words(2, true)),
            'type' => ProductType::Packaged,
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'supplier_id' => Supplier::factory(),
            'unit_id' => Unit::factory(),
            'pack_size' => fake()->randomElement([100, 250, 500, 1000]),
            'pack_unit_id' => Unit::factory()->fractional(),
            'units_per_case' => fake()->randomElement([6, 12, 24]),
            'hs_code' => fake()->numerify('####'),
            'tax_rate' => 15,
            'price_includes_tax' => true,
            'cost_price' => $cost,
            'selling_price' => round($cost * 1.2, 2),
            'opening_stock' => 0,
            'current_stock' => 0,
            'reorder_level' => fake()->numberBetween(5, 25),
            'max_stock_level' => fake()->numberBetween(50, 200),
            'is_weighable' => false,
            'wastage_percent' => 0,
            'track_batches' => false,
            'track_expiry' => false,
            'storage_type' => StorageType::Ambient,
            'rack_location' => 'Aisle '.fake()->numberBetween(1, 8),
            'is_active' => true,
        ];
    }

    /**
     * Produce sold by weight rather than by the packet.
     */
    public function loose(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sku' => 'LSE-'.fake()->unique()->numerify('#####'),
            'barcode' => null,
            'type' => ProductType::Loose,
            'brand_id' => null,
            'pack_size' => null,
            'pack_unit_id' => null,
            'units_per_case' => null,
            'is_weighable' => true,
            'min_sale_quantity' => 0.25,
            'wastage_percent' => 5,
        ]);
    }

    /**
     * A perishable line that carries batch and expiry tracking.
     */
    public function perishable(int $shelfLifeDays = 30): static
    {
        return $this->state(fn (array $attributes): array => [
            'track_batches' => true,
            'track_expiry' => true,
            'shelf_life_days' => $shelfLifeDays,
            'storage_type' => StorageType::Chilled,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
