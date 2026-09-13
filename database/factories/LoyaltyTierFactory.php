<?php

namespace Database\Factories;

use App\Enums\CardTheme;
use App\Models\LoyaltyTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyTier>
 */
class LoyaltyTierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'min_spend' => 0,
            'earn_multiplier' => 1,
            'card_theme' => CardTheme::Emerald,
            'perks' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * A higher tier reached at the given spend.
     */
    public function reachedAt(float $spend, float $multiplier): static
    {
        return $this->state(fn (array $attributes): array => [
            'min_spend' => $spend,
            'earn_multiplier' => $multiplier,
            'card_theme' => CardTheme::Gold,
        ]);
    }
}
