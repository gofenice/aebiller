<?php

namespace Database\Factories;

use App\Enums\LoyaltyTransactionType;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyTransaction>
 */
class LoyaltyTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $points = fake()->numberBetween(10, 300);

        return [
            'customer_id' => Customer::factory(),
            'type' => LoyaltyTransactionType::Adjustment,
            'points' => $points,
            'balance_after' => $points,
            'points_remaining' => $points,
            'expires_at' => null,
            'description' => 'Goodwill credit',
        ];
    }
}
