<?php

namespace App\Http\Resources\WatchBuy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchBuyStatusSeenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status_id' => (int) $this->resource['status_id'],
            'seen_at' => $this->resource['seen_at'],
        ];
    }
}
