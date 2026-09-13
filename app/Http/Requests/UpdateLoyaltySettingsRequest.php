<?php

namespace App\Http\Requests;

use App\Enums\CardTheme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLoyaltySettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-loyalty');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['nullable', 'boolean'],
            'program_name' => ['required', 'string', 'max:60'],
            'points_per_currency' => ['required', 'numeric', 'min:0', 'max:1000'],
            'point_value' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'min_redeem_points' => ['required', 'integer', 'min:1', 'max:1000000'],
            'max_redeem_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'points_expiry_months' => ['required', 'integer', 'min:0', 'max:120'],
            'welcome_bonus' => ['required', 'integer', 'min:0', 'max:100000'],
            'birthday_bonus' => ['required', 'integer', 'min:0', 'max:100000'],
            'tier_window_months' => ['required', 'integer', 'min:1', 'max:60'],
            'card_terms' => ['nullable', 'string', 'max:600'],

            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.id' => ['nullable', 'integer', 'exists:loyalty_tiers,id'],
            'tiers.*.name' => ['required', 'string', 'max:40'],
            'tiers.*.min_spend' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'tiers.*.earn_multiplier' => ['required', 'numeric', 'min:0', 'max:10'],
            'tiers.*.card_theme' => ['required', Rule::enum(CardTheme::class)],
            'tiers.*.perks' => ['nullable', 'string', 'max:255'],
            'tiers.*.remove' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tiers.*.name.required' => 'Every tier needs a name.',
            'tiers.*.min_spend.required' => 'Every tier needs a qualifying spend (0 for the starting tier).',
        ];
    }

    /**
     * New members start on the tier that needs no spend, so one must remain,
     * and two tiers at the same spend would leave members between them.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $kept = collect($this->input('tiers', []))->reject(fn (array $tier): bool => (bool) ($tier['remove'] ?? false));

                if ($kept->isEmpty()) {
                    $validator->errors()->add('tiers', 'Keep at least one tier.');

                    return;
                }

                $spends = $kept->map(fn (array $tier): float => round((float) ($tier['min_spend'] ?? 0), 2));

                if (! $spends->contains(0.0)) {
                    $validator->errors()->add('tiers', 'One tier must start at 0 spend — it is where new members begin.');
                }

                if ($spends->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('tiers', 'Two tiers cannot share the same qualifying spend.');
                }
            },
        ];
    }
}
