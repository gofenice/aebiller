<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LoyaltyRedemptionOtp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<LoyaltyRedemptionOtp>
 */
class LoyaltyRedemptionOtpFactory extends Factory
{
    /**
     * The code every factory-made row answers to, so a test can type it.
     */
    public const CODE = '123456';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'points' => 100,
            'code_hash' => Hash::make(self::CODE),
            'sent_to' => '919812345678',
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ];
    }

    /**
     * The member has already read the code back to the cashier.
     */
    public function verified(): static
    {
        return $this->state(fn (): array => ['verified_at' => now()]);
    }

    /**
     * Already spent on a bill.
     */
    public function consumed(): static
    {
        return $this->state(fn (): array => [
            'verified_at' => now()->subMinute(),
            'consumed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function forPoints(int $points): static
    {
        return $this->state(fn (): array => ['points' => $points]);
    }
}
