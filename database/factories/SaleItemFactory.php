<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 5);
        $price = fake()->randomFloat(2, 5, 60);
        $total = round($quantity * $price, 2);
        $vat = round($total * 15 / 115, 2);

        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'name' => fake()->words(2, true),
            'sku' => 'PKT-'.fake()->unique()->numerify('#####'),
            'unit_code' => 'pc',
            'quantity' => $quantity,
            'unit_price' => $price,
            'vat_rate' => 15,
            'line_subtotal' => round($total - $vat, 2),
            'line_vat' => $vat,
            'line_total' => $total,
            'unit_cost' => round($price * 0.8, 2),
        ];
    }
}
