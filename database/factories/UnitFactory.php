<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'code' => substr($name, 0, 4).fake()->unique()->numberBetween(1, 999),
            'allows_decimal' => false,
            'is_active' => true,
        ];
    }

    /**
     * A unit that can hold fractional quantities, such as kilograms.
     */
    public function fractional(): static
    {
        return $this->state(fn (array $attributes): array => ['allows_decimal' => true]);
    }
}
