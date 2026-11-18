<?php

namespace App\Http\Requests\Settings;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateSetting', [Setting::class, 'payment']) === true;
    }

    public function rules(): array
    {
        return [
            'gateway' => ['required', 'string', Rule::in(['xendit', 'mercado_pago'])],
            'secret_key' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function secretKey(): string
    {
        return trim((string) $this->input('secret_key', ''));
    }

    public function gateway(): string
    {
        return (string) $this->input('gateway');
    }
}
