<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SavePlatformSettingsRequest extends FormRequest
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
            'razorpay_key_id' => ['nullable', 'string', 'max:60', 'starts_with:rzp_'],
            'razorpay_key_secret' => ['nullable', 'string', 'max:200'],
            'razorpay_webhook_secret' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'razorpay_key_id.starts_with' => 'A Razorpay key id begins with rzp_test_ or rzp_live_.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'razorpay_key_id' => 'key id',
            'razorpay_key_secret' => 'key secret',
            'razorpay_webhook_secret' => 'webhook secret',
        ];
    }

    /**
     * Saved secrets are never shown in the form, so an empty box means "leave
     * it as it is", not "clear it".
     */
    protected function prepareForValidation(): void
    {
        foreach (['razorpay_key_secret', 'razorpay_webhook_secret'] as $secret) {
            if (! $this->filled($secret)) {
                $this->request->remove($secret);
            }
        }
    }
}
