<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
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
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'monthly_price' => fake()->randomElement([99, 199, 299, 499]),
            'billing_period' => BillingPeriod::Monthly,
            'currency_code' => config('tenancy.base_currency'),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
