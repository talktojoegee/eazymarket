<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * StoreProductVariant is now a thin mapping (store ↔ variant) — pricing moved
 * to ProductVariant and inventory to StoreLocationInventory. Pre-existing
 * clients that consumed this resource for price/stock should switch to
 * ProductVariantResource (which surfaces both variant-level pricing and the
 * per-store stock aggregate). We keep this resource minimal so legacy
 * consumers don't 500.
 */
class StoreProductVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        $variant = $this->productVariant;
        $stock = $variant?->stockForStore((int) $this->store_id);

        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'product_variant_id' => $this->product_variant_id,
            'sku' => $variant?->sku,
            'price' => $variant?->price,
            'special_price' => $variant?->special_price,
            'cost' => $variant?->cost,
            'stock' => $stock,
            'is_in_stock' => $stock === null || $stock > 0,
        ];
    }
}
