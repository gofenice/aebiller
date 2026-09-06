<?php

namespace App\Http\Requests;

use App\Enums\AdjustmentReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
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
            'adjustment_date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.direction' => ['required', 'in:in,out'],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'items.*.stock_batch_id' => ['nullable', 'exists:stock_batches,id'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one product line to the adjustment.',
            'items.*.product_id.distinct' => 'The same product is listed more than once — merge those lines.',
        ];
    }

    /**
     * A physical count always resolves to the counted figure, so the direction
     * is decided by the service rather than by the user.
     */
    protected function prepareForValidation(): void
    {
        $isCount = $this->input('reason') === AdjustmentReason::StockCount->value;

        $items = collect($this->input('items', []))
            ->filter(fn ($item): bool => ! empty($item['product_id']))
            ->map(function (array $item) use ($isCount): array {
                $item['direction'] = $isCount ? 'in' : ($item['direction'] ?? 'out');

                return $item;
            })
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }
}
