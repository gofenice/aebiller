<?php

namespace Database\Factories;

use App\Enums\StockEntryType;
use App\Models\StockEntry;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockEntry>
 */
class StockEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_no' => 'GRN-'.now()->format('ym').'-'.fake()->unique()->numerify('####'),
            'type' => StockEntryType::Purchase,
            'entry_date' => now()->toDateString(),
            'supplier_id' => Supplier::factory(),
            'invoice_number' => strtoupper(fake()->bothify('INV-####')),
            'invoice_date' => now()->toDateString(),
            'created_by' => User::factory(),
        ];
    }
}
