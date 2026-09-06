<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 50);

        return [
            'product_id' => Product::factory(),
            'type' => MovementType::Purchase,
            'direction' => 'in',
            'quantity' => $quantity,
            'balance_after' => $quantity,
            'unit_cost' => fake()->randomFloat(2, 10, 200),
            'user_id' => User::factory(),
            'moved_at' => now(),
        ];
    }

    public function outward(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MovementType::AdjustmentOut,
            'direction' => 'out',
        ]);
    }
}
