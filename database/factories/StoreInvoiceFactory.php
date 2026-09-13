<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Store;
use App\Models\StoreInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreInvoice>
 */
class StoreInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = now()->startOfMonth();

        return [
            'store_id' => Store::factory(),
            'number' => 'INV-'.now()->format('ym').'-'.fake()->unique()->numerify('####'),
            'period_start' => $periodStart,
            'period_end' => $periodStart->copy()->endOfMonth(),
            'amount' => 199,
            'amount_paid' => 0,
            'currency_code' => 'SAR',
            'status' => InvoiceStatus::Issued,
            'issued_on' => $periodStart,
            'due_on' => $periodStart,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Overdue,
            'due_on' => now()->subDays(10),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Paid,
            'amount_paid' => $attributes['amount'] ?? 199,
            'paid_at' => now(),
        ]);
    }
}
