<?php

namespace Database\Factories;

use App\Enums\PaymentMethodType;
use App\Models\Store;
use App\Models\StorePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorePayment>
 */
class StorePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'amount' => 199,
            'currency_code' => 'SAR',
            'method' => PaymentMethodType::BankTransfer,
            'reference' => fake()->bothify('TRF-####??'),
            'received_on' => now(),
        ];
    }
}
