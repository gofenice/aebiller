<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Support\StoreContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One line of the shop's old due book: who owes, and how much.
 */
class StoreDueBookEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-customers');
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
            'phone' => [
                'required',
                'regex:/^\d{9,15}$/',
                // An existing member is topped up rather than duplicated, so a
                // known number is allowed here.
                Rule::unique('customers', 'phone')->where('store_id', StoreContext::id())->ignore($this->existing()?->id),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'opening_due_on' => ['nullable', 'date', 'before_or_equal:today'],
            'opening_due_note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * The member already registered on this number, if there is one.
     */
    public function existing(): ?Customer
    {
        $phone = Customer::normalizePhone((string) $this->input('phone'));

        return blank($phone) ? null : Customer::where('phone', $phone)->first();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a mobile number of 9 to 15 digits.',
            'amount.min' => 'Enter what this customer owes.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => 'amount owed',
            'opening_due_on' => 'owed as at',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => Customer::normalizePhone((string) $this->input('phone'))]);
        }
    }
}
