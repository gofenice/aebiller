<?php

namespace Database\Factories;

use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppMessage>
 */
class WhatsAppMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'to' => '9198'.fake()->numerify('########'),
            'purpose' => 'bill',
            'template' => 'bill_copy',
            'status' => 'sent',
            'provider_message_id' => 'wamid.'.fake()->uuid(),
            'sent_at' => now(),
        ];
    }

    public function otp(): static
    {
        return $this->state(fn (): array => [
            'purpose' => 'otp',
            'template' => 'redeem_otp',
        ]);
    }

    /**
     * WhatsApp refused it — the case the owner override exists for.
     */
    public function failed(string $error = 'Template not approved'): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'provider_message_id' => null,
            'error' => $error,
            'sent_at' => null,
        ]);
    }
}
