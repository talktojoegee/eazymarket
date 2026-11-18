<?php

namespace App\Http\Resources\Product;

use App\Services\ProductTaxBreakdownService;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerProductVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        $attributes = [];

        if ($this->relationLoaded('attributes')) {
            foreach ($this->attributes as $attribute) {
                if ($attribute->attribute && $attribute->attributeValue) {
                    $attributes[$attribute->attribute->slug] = $attribute->attributeValue->title;
                }
            }
        } else {
            $this->loadMissing(['attributes.attribute', 'attributes.attributeValue']);
            foreach ($this->attributes as $attribute) {
                if ($attribute->attribute && $attribute->attributeValue) {
                    $attributes[$attribute->attribute->slug] = $attribute->attributeValue->title;
                }
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'image' => $this->image ?? '',
            'weight' => (float) ($this->weight ?? 0),
            'height' => (float) ($this->height ?? 0),
            'breadth' => (float) ($this->breadth ?? 0),
            'length' => (float) ($this->length ?? 0),
            'availability' => $this->availability,
            'barcode' => $this->barcode,
            'is_default' => $this->is_default,
            'stores' => SellerStoreProductVariantResource::collection(
                $this->whenLoaded('storeProductVariants', fn () => $this->storeProductVariants)
            ),
            'tax_breakdown' => app(ProductTaxBreakdownService::class)->forVariant($this->resource),
            'attributes' => $attributes,
        ];
    }
}
