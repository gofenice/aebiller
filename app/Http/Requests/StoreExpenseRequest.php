<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-expenses');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],

            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'payee' => ['nullable', 'string', 'max:150'],
            'stock_entry_id' => ['nullable', 'exists:stock_entries,id'],

            'description' => ['required', 'string', 'max:200'],
            'invoice_number' => ['nullable', 'string', 'max:64'],

            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],

            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'is_paid' => ['boolean'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],

            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'expense_category_id' => 'category',
            'supplier_id' => 'supplier',
            'stock_entry_id' => 'goods receipt',
            'amount' => 'amount excluding VAT',
            'vat_rate' => 'VAT rate',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_paid' => $this->boolean('is_paid')]);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (blank($this->input('supplier_id')) && blank($this->input('payee'))) {
                    $validator->errors()->add('payee', 'Say who was paid — pick a supplier or type a name.');
                }
            },
        ];
    }
}
