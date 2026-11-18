<?php

namespace App\Http\Requests\WatchBuy;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateWatchBuyLikesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.reel_id' => ['required', 'integer', 'distinct:strict', 'exists:watch_buy_reels,id'],
            'items.*.liked' => ['required', 'boolean'],
        ];
    }
}
