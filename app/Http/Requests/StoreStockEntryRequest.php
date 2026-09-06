<?php

namespace App\Http\Requests;

use App\Enums\StockEntryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-stock');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(StockEntryType::class)],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'supplier_id' => ['nullable', 'exists:suppliers,id', Rule::requiredIf($this->input('type') === StockEntryType::Purchase->value)],
            'invoice_number' => ['nullable', 'string', 'max:64'],
            'invoice_date' => ['nullable', 'date', 'before_or_equal:today'],
            'other_charges' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'items.*.free_quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.batch_number' => ['nullable', 'string', 'max:64'],
            'items.*.manufactured_on' => ['nullable', 'date', 'before_or_equal:today'],
            'items.*.expires_on' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one product line to the entry.',
            'items.*.product_id.distinct' => 'The same product is listed more than once — merge those lines.',
            'items.*.quantity.gt' => 'Quantity must be greater than zero.',
            'items.*.expires_on.after' => 'Expiry date must be in the future.',
        ];
    }

    /**
     * Only keep the lines the user actually filled in.
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($item): bool => ! empty($item['product_id']))
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }
}
