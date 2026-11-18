<?php

namespace App\Http\Resources\Blog;

use Illuminate\Http\Resources\Json\JsonResource;

class BlogCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'description' => $this->description,
            'image' => $this->image,
            'is_active' => (bool) $this->is_active,
            'blogs_count' => $this->whenCounted('blogs'),
            'metadata' => $this->metadataWithSeo(),
        ];
    }
}
