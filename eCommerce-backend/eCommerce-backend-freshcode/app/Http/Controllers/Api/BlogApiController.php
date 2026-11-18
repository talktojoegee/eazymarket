<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Blog\BlogCategoryResource;
use App\Http\Resources\Blog\BlogResource;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Market;
use App\Models\Product;
use App\Types\Api\ApiResponseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'category_slug' => ['nullable', 'string', 'max:191'],
            'editors_pick' => ['nullable', 'in:true,false,1,0'],
            'tag' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Blog::query()
            ->with(['categories.media', 'authorProfile', 'media'])
            ->published()
            ->where('published_at', '<=', now());

        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];
            $query->where(fn (Builder $blogs) => $blogs
                ->where('title', 'like', "%{$search}%")
                ->orWhere('short_description', 'like', "%{$search}%")
                ->orWhere('content', 'like', "%{$search}%"));
        }

        if (isset($filters['category_id'])) {
            $query->whereHas('categories', fn (Builder $categories) => $categories
                ->where('blog_categories.id', $filters['category_id'])
                ->where('blog_categories.is_active', true));
        }

        if (filled($filters['category_slug'] ?? null)) {
            $query->whereHas('categories', fn (Builder $categories) => $categories
                ->where('blog_categories.slug', $filters['category_slug'])
                ->where('blog_categories.is_active', true));
        }

        if (array_key_exists('editors_pick', $filters)) {
            $query->where('editor_pick', filter_var($filters['editors_pick'], FILTER_VALIDATE_BOOLEAN));
        }

        if (filled($filters['tag'] ?? null)) {
            $query->whereJsonContains('tags', $filters['tag']);
        }

        $blogs = $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return ApiResponseType::sendJsonResponse(true, 'labels.blogs_fetched_successfully', [
            'current_page' => $blogs->currentPage(),
            'last_page' => $blogs->lastPage(),
            'per_page' => $blogs->perPage(),
            'total' => $blogs->total(),
            'data' => BlogResource::collection($blogs->getCollection()),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = BlogCategory::query()
            ->where('is_active', true)
            ->with('media')
            ->withCount(['blogs' => fn (Builder $blogs) => $blogs
                ->published()
                ->where('published_at', '<=', now())])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponseType::sendJsonResponse(true, 'labels.blog_categories_fetched_successfully', [
            'data' => BlogCategoryResource::collection($categories),
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $blog = Blog::query()
            ->with(['categories.media', 'authorProfile', 'media'])
            ->published()
            ->where('published_at', '<=', now())
            ->where('slug', $slug)
            ->first();

        if (! $blog) {
            return ApiResponseType::sendJsonResponse(false, 'labels.blog_not_found', [], 404);
        }

        $productIds = $blog->products()->pluck('products.id')->all();
        $market = $request->attributes->get('market');
        $apiProducts = Product::scopeForMarket(
            $market instanceof Market ? $market : null,
            Product::query(),
        )->whereIn('products.id', $productIds)->get();
        $blog->setRelation('apiProducts', $apiProducts);

        return ApiResponseType::sendJsonResponse(true, 'labels.blog_fetched_successfully', [
            'blog' => new BlogResource($blog),
        ]);
    }
}
