<?php

namespace App\Http\Requests\WatchBuy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWatchBuyPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'published' => ['required', 'boolean'],
        ];
    }
}
