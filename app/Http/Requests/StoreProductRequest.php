<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use App\Enums\StorageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-products');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($this->route('product'))],
            'type' => ['required', Rule::enum(ProductType::class)],

            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'unit_id' => ['required', 'exists:units,id'],

            'pack_size' => ['nullable', 'numeric', 'min:0.001', 'max:99999'],
            'pack_unit_id' => ['nullable', 'exists:units,id', 'required_with:pack_size'],
            'units_per_case' => ['nullable', 'integer', 'min:1', 'max:100000'],

            'hs_code' => ['nullable', 'string', 'max:20'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'price_includes_tax' => ['boolean'],

            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999'],

            'opening_stock' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'max_stock_level' => ['nullable', 'numeric', 'min:0', 'max:9999999'],

            'is_weighable' => ['boolean'],
            'tare_weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'min_sale_quantity' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'wastage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'track_batches' => ['boolean'],
            'track_expiry' => ['boolean'],
            'shelf_life_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'opening_expires_on' => ['nullable', 'date', 'after_or_equal:today'],

            'storage_type' => ['required', Rule::enum(StorageType::class)],
            'rack_location' => ['nullable', 'string', 'max:60'],

            'image' => ['nullable', 'image', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Prepare boolean toggles that browsers omit when unchecked.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // Shelf prices are quoted VAT inclusive, which is the figure the
            // product form collects and the till charges.
            'price_includes_tax' => true,
            'is_weighable' => $this->boolean('is_weighable'),
            'track_batches' => $this->boolean('track_batches'),
            'track_expiry' => $this->boolean('track_expiry'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * Guard the rules that only make sense once other fields are known.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') === ProductType::Packaged->value && ! $this->filled('pack_size')) {
                    $validator->errors()->add('pack_size', 'Pack size is required for packet products.');
                }

                if ($this->boolean('track_expiry') && ! $this->filled('shelf_life_days') && ! $this->boolean('track_batches')) {
                    $validator->errors()->add('shelf_life_days', 'Give a shelf life (days) so expiry dates can be worked out.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'brand_id' => 'brand',
            'supplier_id' => 'supplier',
            'unit_id' => 'selling unit',
            'pack_unit_id' => 'pack unit',
        ];
    }
}
