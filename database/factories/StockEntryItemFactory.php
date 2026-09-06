<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockEntry;
use App\Models\StockEntryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockEntryItem>
 */
class StockEntryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 50);
        $unitCost = fake()->randomFloat(2, 10, 300);

        return [
            'stock_entry_id' => StockEntry::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'free_quantity' => 0,
            'unit_cost' => $unitCost,
            'discount_percent' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'line_total' => round($quantity * $unitCost, 2),
        ];
    }
}
