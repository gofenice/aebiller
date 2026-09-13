<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustPointsRequest extends FormRequest
{
    /**
     * Manual credits are money given away, so only a super admin may enter them.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-loyalty');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'points.not_in' => 'Enter the number of points to add, or a negative number to take away.',
            'reason.required' => 'Say why the points are being changed — it is kept on the member\'s history.',
        ];
    }
}
