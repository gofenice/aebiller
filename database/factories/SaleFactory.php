<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 20, 400);
        $vat = round($total * 15 / 115, 2);

        return [
            'invoice_no' => 'INV-'.now()->format('ym').'-'.fake()->unique()->numerify('####'),
            'status' => SaleStatus::Completed,
            'items_gross' => $total,
            'subtotal_excl_vat' => round($total - $vat, 2),
            'vat_total' => $vat,
            'grand_total' => $total,
            'payment_method' => PaymentMethod::Cash,
            'amount_paid' => $total,
            'cashier_id' => User::factory(),
            'sold_at' => now(),
        ];
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SaleStatus::Voided,
            'voided_at' => now(),
        ]);
    }
}
