<?php

namespace App\Http\Requests\Platform;

use App\Enums\BillingPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SavePlanRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:60'],
            'slug' => [
                'required', 'string', 'max:60',
                Rule::unique('plans', 'slug')->ignore($this->route('plan')),
            ],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'billing_period' => ['required', Rule::enum(BillingPeriod::class)],

            // What a year costs against twelve months of the monthly price.
            'yearly_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Blank means no limit at all on this plan.
            'max_products' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'max_monthly_bills' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'max_users' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'max_customers' => ['nullable', 'integer', 'min:0', 'max:100000000'],

            'is_public' => ['nullable', 'boolean'],
            'is_free' => ['nullable', 'boolean'],

            // One optional price per offered currency; blank means "use the base price".
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'currency_code' => ['required', Rule::in(array_keys(config('tenancy.currencies')))],
            'description' => ['nullable', 'string', 'max:200'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->filled('slug') ? $this->string('slug')->toString() : $this->string('name')->toString()),
        ]);
    }
}
