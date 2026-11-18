<?php

namespace App\Http\Resources\WatchBuy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchBuyLikeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reel_id' => (int) $this->resource['reel_id'],
            'like_count' => (int) $this->resource['like_count'],
            'liked_by_me' => (bool) $this->resource['liked_by_me'],
        ];
    }
}
