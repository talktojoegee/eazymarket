<?php

namespace App\Services;

use App\Models\Blog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BlogService
{
    public function save(Blog $blog, array $attributes, ?UploadedFile $image = null): Blog
    {
        $removeImage = (bool) ($attributes['remove_image'] ?? false);
        $categoryIds = array_values(array_unique(array_map('intval', $attributes['category_ids'] ?? [])));
        $productIds = array_values(array_unique(array_map('intval', $attributes['product_ids'] ?? [])));
        $tags = array_values(array_unique(array_filter(array_map(
            fn ($tag) => trim((string) $tag),
            $attributes['tags'] ?? []
        ))));
        $attributes['slug'] = filled($attributes['slug'] ?? null)
            ? Blog::uniqueSlug($attributes['slug'], $blog->id)
            : Blog::uniqueSlug($attributes['title'], $blog->id);
        $attributes['editor_pick'] = filter_var($attributes['editor_pick'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $attributes['published_at'] = $attributes['status'] === 'published'
            ? ($attributes['published_at'] ?? now())
            : null;
        $attributes['tags'] = $tags;
        $attributes['metadata'] = array_filter(
            (array) ($attributes['metadata'] ?? []),
            fn ($value) => $value !== null && $value !== ''
        );
        unset($attributes['image'], $attributes['remove_image'], $attributes['category_ids'], $attributes['product_ids']);

        DB::transaction(function () use ($blog, $attributes, $categoryIds, $productIds, $image, $removeImage) {
            $blog->fill($attributes)->save();
            $blog->categories()->sync($categoryIds);
            $blog->products()->sync($productIds);

            if ($image) {
                $blog->addMedia($image)->toMediaCollection('blog_cover_image');
            } elseif ($removeImage) {
                $blog->clearMediaCollection('blog_cover_image');
            }
        });

        return $blog->refresh();
    }

    public function delete(Blog $blog): void
    {
        $blog->clearMediaCollection('blog_cover_image');
        $blog->delete();
    }
}
