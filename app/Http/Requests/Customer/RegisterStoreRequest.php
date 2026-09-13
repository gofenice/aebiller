<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterStoreRequest extends FormRequest
{
    /**
     * Anyone may sign their shop up.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'min:3', 'max:40',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::notIn(config('tenancy.reserved_subdomains')),
                Rule::unique('stores', 'slug'),
            ],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:150'],
            'owner_phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
            // Only a plan a shop may pick for itself: the free lifetime plan is
            // given out from the platform, never chosen off the sign-up form.
            'plan' => [
                'nullable',
                Rule::exists('plans', 'slug')->where(function ($query): void {
                    $query->where('is_active', true)->where('is_public', true);
                }),
            ],
            'period' => ['nullable', Rule::in(['monthly', 'yearly'])],
            // No currency here: it comes from the switcher on the public site,
            // so there is only one answer and it cannot contradict the prices
            // the shop was just quoted.
            'timezone' => ['required', Rule::in(config('tenancy.timezones'))],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The address may use only lowercase letters, numbers and dashes — for example "al-noor".',
            'slug.not_in' => 'That address is reserved. Please choose another.',
            'slug.unique' => 'That address is already taken. Please choose another.',
            'terms.accepted' => 'Please accept the terms to continue.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'shop name',
            'slug' => 'shop address',
            'owner_name' => 'your name',
            'owner_email' => 'email',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug(Str::lower($this->string('slug')->toString())),
            'timezone' => $this->filled('timezone') ? $this->string('timezone')->toString() : config('tenancy.defaults.timezone'),
        ]);
    }
}
