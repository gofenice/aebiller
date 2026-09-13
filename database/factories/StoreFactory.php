<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $defaults = config('tenancy.defaults');

        return [
            'name' => $name,
            'slug' => Str::slug(Str::limit($name, 20, '')).'-'.fake()->unique()->numberBetween(1, 99999),
            'status' => StoreStatus::Active,
            'owner_name' => fake()->name(),
            'owner_email' => fake()->unique()->safeEmail(),
            'owner_phone' => fake()->numerify('05########'),
            'currency_code' => $defaults['currency_code'],
            'currency_symbol' => $defaults['currency_symbol'],
            'timezone' => $defaults['timezone'],
            'expiry_alert_days' => $defaults['expiry_alert_days'],
            'tax_rates' => $defaults['tax_rates'],
        ];
    }

    public function suspended(?string $reason = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StoreStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason ?? 'Monthly payment overdue',
        ]);
    }
}
