<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('run-till');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'bill_discount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],

            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'customer_vat_number' => ['nullable', 'string', 'max:20'],
            // Credit is chased through a member's account, so it needs one.
            'customer_id' => [
                Rule::requiredIf(fn (): bool => $this->string('payment_method')->toString() === PaymentMethod::Credit->value),
                'nullable', 'integer', 'exists:customers,id',
            ],
            'redeem_points' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            // The code the member approved this redemption with. Checked again
            // against the member and the points when the bill is written.
            'redemption_otp_id' => ['nullable', 'integer', 'exists:loyalty_redemption_otps,id'],
            'notes' => ['nullable', 'string', 'max:500'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Credit needs a loyalty member on the bill — attach the customer first.',
            'items.required' => 'Scan at least one product before taking payment.',
            'items.*.product_id.distinct' => 'The same product is listed twice — change the quantity instead.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($item): bool => ! empty($item['product_id']))
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }
}
