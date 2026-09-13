<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LoyaltyCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('05########'),
            'email' => fake()->optional()->safeEmail(),
            'birth_date' => fake()->optional()->dateTimeBetween('-70 years', '-18 years'),
            'city' => fake()->randomElement(['Riyadh', 'Jeddah', 'Dammam', 'Khobar']),
            'marketing_opt_in' => fake()->boolean(),
            'is_active' => true,
        ];
    }

    /**
     * A member whose membership has been put on hold.
     */
    public function onHold(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * A member holding an active card.
     */
    public function withCard(): static
    {
        return $this->has(LoyaltyCard::factory(), 'cards');
    }
}
