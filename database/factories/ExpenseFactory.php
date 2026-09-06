<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 20, 900);
        $vat = round($amount * 0.15, 2);

        return [
            'reference_no' => 'EXP-'.now()->format('ym').'-'.fake()->unique()->numerify('####'),
            'expense_date' => now()->toDateString(),
            'expense_category_id' => ExpenseCategory::factory(),
            'payee' => fake()->company(),
            'description' => fake()->sentence(4),
            'amount' => $amount,
            'vat_rate' => 15,
            'vat_amount' => $vat,
            'total' => round($amount + $vat, 2),
            'payment_method' => PaymentMethod::Cash,
            'is_paid' => true,
            'paid_on' => now()->toDateString(),
            'created_by' => User::factory(),
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_paid' => false,
            'paid_on' => null,
        ]);
    }
}
