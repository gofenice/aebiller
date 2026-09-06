<?php

namespace App\Http\Requests;

class UpdateProductRequest extends StoreProductRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Stock is only moved through stock entries and adjustments once a
        // product exists, so the opening-stock fields are dropped here.
        unset($rules['opening_stock'], $rules['opening_expires_on']);

        return $rules;
    }
}
