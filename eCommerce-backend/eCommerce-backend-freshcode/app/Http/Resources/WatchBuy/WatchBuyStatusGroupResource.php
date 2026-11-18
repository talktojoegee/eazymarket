<?php

namespace App\Http\Resources\WatchBuy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchBuyStatusGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'profile' => new WatchBuyProfileResource($this->resource),
            'status_count' => (int) $this->resource->status_count,
        ];
    }
}
