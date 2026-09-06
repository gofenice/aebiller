<?php

namespace Database\Factories;

use App\Enums\AdjustmentReason;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_no' => 'ADJ-'.now()->format('ym').'-'.fake()->unique()->numerify('####'),
            'adjustment_date' => now()->toDateString(),
            'reason' => AdjustmentReason::Damage,
            'total_value' => 0,
            'created_by' => User::factory(),
        ];
    }
}
