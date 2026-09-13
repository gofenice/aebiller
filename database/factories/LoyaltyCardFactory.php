<?php

namespace Database\Factories;

use App\Enums\CardStatus;
use App\Models\Customer;
use App\Models\LoyaltyCard;
use App\Services\BarcodeGenerator;
use App\Services\LoyaltyService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyCard>
 */
class LoyaltyCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = LoyaltyService::CARD_PREFIX.fake()->unique()->numerify('##########');

        return [
            'customer_id' => Customer::factory(),
            'number' => $body.app(BarcodeGenerator::class)->checkDigit($body),
            'status' => CardStatus::Active,
            'issued_at' => now(),
        ];
    }

    /**
     * A card the member reported lost.
     */
    public function lost(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CardStatus::Lost,
            'retired_at' => now(),
            'retired_reason' => 'Reported lost',
        ]);
    }
}
