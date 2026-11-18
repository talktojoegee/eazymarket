<?php

namespace App\Http\Resources\User;

use App\Models\Market;
use App\Models\Review;
use App\Support\PricePresenter;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $reviews = Review::scopeProductRatingStats($this->product->id);

        return [
            'id' => $this->id,
            'wishlist_id' => $this->wishlist_id,
            'product' => [
                'id' => $this->product->id,
                'title' => $this->product->title,
                'slug' => $this->product->slug,
                'image' => $this->product->main_image,
                'minimum_order_quantity' => $this->product->minimum_order_quantity,
                'quantity_step_size' => $this->product->quantity_step_size,
                'total_allowed_quantity' => $this->product->total_allowed_quantity,
                'short_description' => $this->product->short_description,
                'estimated_delivery_time' => $this->product->estimated_delivery_time,
                'image_fit' => $this->product->image_fit,
                'ratings' => (float) ($reviews['average_rating'] ?? 0),
                'rating_count' => (int) ($reviews['total_reviews'] ?? 0),
            ],
            'variant' => $this->variant ? (function () use ($request) {
                $variant = $this->variant;
                $firstMapping = $variant?->storeProductVariants->first();
                $storeId = $firstMapping?->store_id ?? $this->store_id;
                $stock = $variant?->stockForStore($storeId);

                $market = $request?->attributes->get('market');
                $price        = $market instanceof Market
                    ? PricePresenter::forMarket((float) ($variant?->price ?? 0), $market)
                    : PricePresenter::forCurrent((float) ($variant?->price ?? 0));
                $specialPrice = $market instanceof Market
                    ? PricePresenter::forMarket((float) ($variant?->special_price ?? 0), $market)
                    : PricePresenter::forCurrent((float) ($variant?->special_price ?? 0));

                return [
                    'id' => $variant?->id,
                    'sku' => $variant?->sku,
                    'image' => $variant?->image,
                    'price' => $price['price'],
                    'price_base' => $price['price_base'],
                    'special_price' => $specialPrice['price'],
                    'special_price_base' => $specialPrice['price_base'],
                    'currency_code' => $price['currency_code'],
                    'currency_symbol' => $price['currency_symbol'],
                    'fx_rate' => $price['fx_rate'],
                    'fx_fallback' => (bool) ($price['fx_fallback'] ?? false),
                    'formatted' => $price['formatted'] ?? null,
                    'formatted_special_price' => $specialPrice['formatted'] ?? null,
                    'format' => $price['format'] ?? null,
                    'store_id' => $storeId,
                    'store_slug' => $firstMapping?->store->slug ?? null,
                    'store_name' => $firstMapping?->store->name ?? null,
                    'stock' => $stock,
                    'is_in_stock' => $stock === null || $stock > 0,
                ];
            })() : null,
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
