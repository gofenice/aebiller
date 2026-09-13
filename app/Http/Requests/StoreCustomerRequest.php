<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Support\StoreContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
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
                Rule::unique('customers', 'phone')->where('store_id', StoreContext::id())->ignore($this->route('customer')),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a mobile number of 9 to 15 digits.',
            'phone.unique' => 'Another member is already registered with this mobile number.',
            'birth_date.before' => 'The date of birth must be in the past.',
        ];
    }

    /**
     * Validate the number the way it will be stored, so "055 123 4567" is
     * caught as a duplicate of "0551234567".
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => Customer::normalizePhone((string) $this->input('phone'))]);
        }
    }
}
