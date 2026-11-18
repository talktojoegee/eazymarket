<?php

namespace App\Http\Resources\Blog;

use App\Http\Resources\Product\ProductListResource;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'content' => $this->when($request->routeIs('api.blogs.show'), $this->content),
            'image' => $this->image,
            'status' => $this->status,
            'editor_pick' => (bool) $this->editor_pick,
            'published_at' => $this->published_at,
            'tags' => $this->tags ?? [],
            'metadata' => $this->metadataWithSeo(),
            'categories' => BlogCategoryResource::collection($this->whenLoaded('categories')),
            'author' => $this->whenLoaded('authorProfile', fn () => [
                'id' => $this->authorProfile?->id,
                'username' => $this->authorProfile?->username,
                'bio' => $this->authorProfile?->bio,
                'photo_url' => $this->authorProfile?->photo_url,
            ]),
            'products' => ProductListResource::collection($this->whenLoaded('apiProducts')),
        ];
    }
}
