<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'image' => $this->home_builder_image ?? $this->image ?? '',
            'banner' => $this->banner ?? '',
            'parent_id' => $this->parent_id,
            'commission' => $this->commission ?? 0,
            'parent_slug' => $this->parent->slug ?? null,
            'description' => $this->description,
            'status' => $this->status,
            'requires_approval' => $this->requires_approval,
            'metadata' => $this->metadataWithSeo(),
            'subcategory_count' => $this->children_count ?? 0,
            'product_count' => $this->products_count ?? 0,
            'enabled' => (bool) data_get($this->resource, 'enabled', true),
        ];
    }
}
