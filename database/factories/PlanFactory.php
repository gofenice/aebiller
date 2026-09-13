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
            'yearly_discount_percent' => 0,
            'billing_period' => BillingPeriod::Monthly,
            'currency_code' => config('tenancy.base_currency'),
            'is_active' => true,
            'is_public' => true,
            'is_free' => false,
            'sort_order' => 0,
            // Unlimited unless a test says otherwise.
            'max_products' => null,
            'max_monthly_bills' => null,
            'max_users' => null,
            'max_customers' => null,
        ];
    }

    /**
     * A plan that saves money when paid for a year at a time.
     */
    public function withYearlyDiscount(float $percent = 20): static
    {
        return $this->state(fn (): array => ['yearly_discount_percent' => $percent]);
    }

    /**
     * The plan given out by hand: free forever, never on the pricing table.
     */
    public function lifetimeFree(): static
    {
        return $this->state(fn (): array => [
            'monthly_price' => 0,
            'billing_period' => BillingPeriod::Lifetime,
            'is_free' => true,
            'is_public' => false,
        ]);
    }

    /**
     * @param  array<string, int|null>  $limits
     */
    public function limited(array $limits): static
    {
        return $this->state(fn (): array => $limits);
    }
}
