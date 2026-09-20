<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\CreditPayment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditPayment>
 */
class CreditPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'payment_method' => PaymentMethod::Cash,
            'received_by' => User::factory(),
            'received_at' => now(),
        ];
    }
}
