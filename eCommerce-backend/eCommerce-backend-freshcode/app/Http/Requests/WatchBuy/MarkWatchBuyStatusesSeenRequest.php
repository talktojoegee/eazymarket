<?php

namespace App\Http\Requests\WatchBuy;

use App\Http\Requests\Concerns\RespondsWithApiEnvelope;
use Illuminate\Foundation\Http\FormRequest;

class MarkWatchBuyStatusesSeenRequest extends FormRequest
{
    use RespondsWithApiEnvelope;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_ids' => ['required', 'array', 'min:1', 'max:100'],
            'status_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
