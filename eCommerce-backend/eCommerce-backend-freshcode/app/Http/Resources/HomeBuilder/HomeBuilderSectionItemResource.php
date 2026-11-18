<?php

namespace App\Http\Resources\HomeBuilder;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CurrencyService;
use App\Services\HomeBuilderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeBuilderSectionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $classToKey = array_flip(HomeBuilderService::ITEMABLE_MAP);

        return [
            'id' => $this->id,
            'purpose' => $this->purpose,
            'itemable_type' => $this->itemable_type ? ($classToKey[$this->itemable_type] ?? null) : null,
            'itemable_id' => $this->itemable_id,
            'itemable_label' => $this->itemable?->title ?? $this->itemable?->name,
            'itemable_image' => $this->itemableImage(),
            'itemable_meta' => $this->itemablePreviewMeta(),
            'title' => $this->title,
            'link_url' => $this->link_url,
            'image' => $this->image,
            'mobile_image' => $this->mobile_image,
            'sort_order' => $this->sort_order,
            'config' => $this->config ?? [],
        ];
    }

    private function itemableImage(): ?string
    {
        return match (true) {
            $this->itemable instanceof Category => $this->itemable->image,
            $this->itemable instanceof Brand => $this->itemable->logo,
            $this->itemable instanceof Product => $this->itemable->main_image,
            default => null,
        };
    }

    private function itemablePreviewMeta(): array
    {
        if (! $this->itemable instanceof Product) {
            return [];
        }

        $product = $this->itemable;
        $variant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();
        $price = (float) ($variant?->price ?? 0);
        $specialPrice = (float) ($variant?->special_price ?? 0);
        $hasDiscount = $specialPrice > 0 && $specialPrice < $price;
        $reviews = $product->reviews;

        return [
            'brand' => $product->brand?->title,
            'category' => $product->category?->title,
            'short_description' => trim(preg_replace('/\s+/', ' ', strip_tags((string) $product->short_description))),
            'detail_tags' => collect($product->tags)->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')->take(3)->values()->all(),
            'rating' => $reviews->isNotEmpty() ? round((float) $reviews->avg('rating'), 1) : null,
            'rating_count' => $reviews->count(),
            'is_out_of_stock' => $this->productIsOutOfStock($variant),
            'price' => $price,
            'special_price' => $specialPrice,
            'formatted_price' => app(CurrencyService::class)->format($price),
            'formatted_special_price' => $hasDiscount ? app(CurrencyService::class)->format($specialPrice) : null,
            'discount_percent' => $hasDiscount && $price > 0 ? (int) round((($price - $specialPrice) / $price) * 100) : 0,
            'image_fit' => $product->image_fit ?: 'contain',
            'badge' => $product->badge ? [
                'label' => $product->badge->label,
                'bg_color' => $product->badge->bg_color,
                'text_color' => $product->badge->text_color,
                'border_color' => $product->badge->border_color,
            ] : null,
        ];
    }

    private function productIsOutOfStock(?ProductVariant $variant): bool
    {
        if (! $variant || $variant->availability === false) {
            return true;
        }

        if (! $variant->relationLoaded('locationInventories')) {
            return false;
        }

        $inventory = $variant->locationInventories->where('is_fulfillment_location', true);

        if ($inventory->isEmpty() || $inventory->contains(fn ($row) => ! $row->tracked)) {
            return false;
        }

        return $inventory->sum(fn ($row) => max(0, (int) $row->stock - (int) $row->reserved)) <= 0;
    }
}
