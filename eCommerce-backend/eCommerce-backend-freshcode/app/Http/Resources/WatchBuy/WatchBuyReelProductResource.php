<?php

namespace App\Http\Resources\WatchBuy;

use App\Support\PricePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchBuyReelProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = PricePresenter::forCurrent((float) ($this->variant?->price ?? 0));
        $specialPrice = PricePresenter::forCurrent((float) ($this->variant?->special_price ?? 0));

        return [
            'product_id' => $this->product_id,
            'product_slug' => $this->product?->slug,
            'title' => $this->product?->title,
            'image' => $this->product?->main_image,
            'store_id' => $this->store_id,
            'store_slug' => $this->store?->slug,
            'store_name' => $this->store?->name,
            'variant_id' => $this->product_variant_id,
            'variant_title' => $this->variant?->title,
            'price' => $price['price'],
            'special_price' => $specialPrice['price'],
            'currency_code' => $price['currency_code'],
            'currency_symbol' => $price['currency_symbol'],
            'available' => (bool) ($this->variant?->availability ?? true),
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
        ];
    }
}
