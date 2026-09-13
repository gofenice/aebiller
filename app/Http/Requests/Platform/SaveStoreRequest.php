<?php

namespace App\Http\Requests\Platform;

use App\Enums\BillingPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::guard('platform')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $store = $this->route('store');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'min:3', 'max:40',
                // A subdomain: letters, digits and dashes, never starting or ending with one.
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::notIn(config('tenancy.reserved_subdomains')),
                Rule::unique('stores', 'slug')->ignore($store),
            ],
            'legal_name' => ['nullable', 'string', 'max:150'],

            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:150'],
            'owner_phone' => ['nullable', 'string', 'max:20'],

            'currency_code' => ['required', Rule::in(array_keys(config('tenancy.currencies')))],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'timezone' => ['required', Rule::in(config('tenancy.timezones'))],
            'expiry_alert_days' => ['required', 'integer', 'min:1', 'max:365'],

            'vat_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // What the store pays the platform.
            'plan_id' => ['nullable', 'exists:plans,id'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'billing_currency' => ['nullable', Rule::in(array_keys(config('tenancy.currencies')))],
            'billing_period' => ['nullable', Rule::enum(BillingPeriod::class)],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'invoice_lead_days' => ['nullable', 'integer', 'min:0', 'max:60'],
            'prorate_first_invoice' => ['nullable', 'boolean'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'auto_suspend' => ['nullable', 'boolean'],
            'next_invoice_on' => ['nullable', 'date'],

            // The shop's first sign-in, created with the store itself.
            'admin_name' => [Rule::requiredIf(fn (): bool => $this->routeIs('platform.stores.store')), 'nullable', 'string', 'max:100'],
            'admin_email' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('platform.stores.store')),
                'nullable', 'email', 'max:150',
            ],
            'admin_password' => ['nullable', 'string', 'min:8', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The address may use only lowercase letters, numbers and dashes — for example "al-noor".',
            'slug.not_in' => 'That address is reserved by the platform. Choose another.',
            'slug.unique' => 'Another store already uses that address.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slug' => 'store address',
            'billing_day' => 'billing day of the month',
            'next_invoice_on' => 'first invoice date',
            'admin_name' => "owner's name",
            'admin_email' => "owner's sign-in email",
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge(['slug' => Str::slug(Str::lower($this->string('slug')->toString()))]);
        }

        // A currency's symbol follows its code unless one was typed in.
        if ($this->filled('currency_code') && ! $this->filled('currency_symbol')) {
            $currency = config('tenancy.currencies.'.$this->string('currency_code')->toString());

            $this->merge(['currency_symbol' => $currency['symbol'] ?? null]);
        }
    }
}
