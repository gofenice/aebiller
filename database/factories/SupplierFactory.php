<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SUP-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'phone' => fake()->numerify('05########'),
            'email' => fake()->unique()->companyEmail(),
            'vat_number' => '3'.fake()->numerify('#############').'3',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'pincode' => fake()->numerify('#####'),
            'payment_terms_days' => fake()->randomElement([0, 7, 15, 30]),
            'opening_balance' => 0,
            'is_active' => true,
        ];
    }
}
