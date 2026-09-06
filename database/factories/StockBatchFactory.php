<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBatch>
 */
class StockBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 100);

        return [
            'product_id' => Product::factory(),
            'batch_number' => strtoupper(fake()->bothify('B##??')),
            'manufactured_on' => now()->subDays(30)->toDateString(),
            'expires_on' => now()->addMonths(6)->toDateString(),
            'received_quantity' => $quantity,
            'quantity' => $quantity,
            'cost_price' => fake()->randomFloat(2, 10, 300),
            'received_on' => now()->toDateString(),
        ];
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_on' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_on' => now()->subDays(3)->toDateString(),
        ]);
    }
}
