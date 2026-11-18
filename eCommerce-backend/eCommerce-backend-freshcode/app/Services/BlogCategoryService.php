<?php

namespace App\Services;

use App\Models\BlogCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class BlogCategoryService
{
    public function create(array $attributes, ?UploadedFile $image = null): BlogCategory
    {
        $attributes = $this->normalise($attributes);
        $attributes['slug'] = filled($attributes['slug'] ?? null)
            ? $attributes['slug']
            : BlogCategory::uniqueSlug($attributes['name']);
        $category = BlogCategory::query()->create($attributes);

        if ($image) {
            $category->addMedia($image)->toMediaCollection('blog_category_image');
        }

        return $category->refresh();
    }

    public function update(BlogCategory $category, array $attributes, ?UploadedFile $image = null): BlogCategory
    {
        $removeImage = (bool) ($attributes['remove_image'] ?? false);
        $attributes = $this->normalise($attributes);
        $attributes['slug'] = filled($attributes['slug'] ?? null)
            ? $attributes['slug']
            : BlogCategory::uniqueSlug($attributes['name'], $category->id);
        $category->update($attributes);

        if ($image) {
            $category->addMedia($image)->toMediaCollection('blog_category_image');
        } elseif ($removeImage) {
            $category->clearMediaCollection('blog_category_image');
        }

        return $category->refresh();
    }

    public function delete(BlogCategory $category): void
    {
        if ($category->blogs()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('labels.blog_category_has_blogs'),
            ]);
        }

        $category->clearMediaCollection('blog_category_image');
        $category->delete();
    }

    private function normalise(array $attributes): array
    {
        $attributes['is_active'] = filter_var($attributes['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $attributes['sort_order'] = (int) ($attributes['sort_order'] ?? 0);
        $attributes['metadata'] = array_filter(
            (array) ($attributes['metadata'] ?? []),
            fn ($value) => $value !== null && $value !== ''
        );
        unset($attributes['image'], $attributes['remove_image']);

        return $attributes;
    }
}
