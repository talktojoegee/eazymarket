<?php

namespace App\Http\Requests\WatchBuy;

use App\Http\Requests\Concerns\RespondsWithApiEnvelope;
use Illuminate\Foundation\Http\FormRequest;

class WatchBuyStatusListRequest extends FormRequest
{
    use RespondsWithApiEnvelope;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
