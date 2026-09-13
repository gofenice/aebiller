<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'GBP', 'EUR']),
            'amount' => fake()->randomElement([69, 145, 199, 259]),
        ];
    }

    /**
     * A price in one named currency.
     */
    public function in(string $currency, float $amount): static
    {
        return $this->state(fn (): array => [
            'currency_code' => $currency,
            'amount' => $amount,
        ]);
    }
}
