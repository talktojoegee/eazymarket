<?php

namespace App\Http\Resources\WatchBuy;

use App\Services\WatchBuyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchBuyEligibleProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_title' => $this->product->title,
            'product_image' => $this->product->main_image,
            'variant_id' => $this->id,
            'variant_title' => $this->title,
            'is_out_of_stock' => app(WatchBuyService::class)->productVariantIsOutOfStock($this->resource),
        ];
    }
}
