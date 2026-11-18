<?php

namespace App\Services\Search\Providers;

use App\Models\Category;
use App\Models\User;
use App\Services\Search\Contracts\SearchProvider;
use Illuminate\Support\Str;

class CategorySearchProvider implements SearchProvider
{
    public function search(string $query, string $panel, User $user, int $limit = 5): array
    {
        $term = addcslashes($query, '%_\\');
        $singular = addcslashes(Str::singular($query), '%_\\');

        return Category::query()
            ->where(fn ($builder) => $builder
                ->where('title', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$singular}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"))
            ->limit($limit)
            ->get()
            ->map(fn (Category $category) => [
                'type' => 'category', 'category' => 'Categories', 'badge' => 'CATEGORY',
                'badge_class' => 'bg-cyan-lt', 'icon' => 'ti ti-category-2',
                'title' => $category->title,
                'subtitle' => 'Slug: '.($category->slug ?: 'N/A'),
                'url' => route("{$panel}.categories.index"),
            ])->all();
    }
}
