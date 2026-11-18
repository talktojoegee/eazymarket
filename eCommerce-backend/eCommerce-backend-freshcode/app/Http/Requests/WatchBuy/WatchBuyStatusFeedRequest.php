<?php

namespace App\Http\Requests\WatchBuy;

use App\Http\Requests\Concerns\RespondsWithApiEnvelope;
use Illuminate\Foundation\Http\FormRequest;

class WatchBuyStatusFeedRequest extends FormRequest
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
            'session_id' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
