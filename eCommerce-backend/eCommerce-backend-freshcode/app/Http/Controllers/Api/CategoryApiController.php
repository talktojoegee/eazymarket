<?php

namespace App\Http\Controllers\Api;

use App\Enums\Category\CategorySubCategoryFilterEnum;
use App\Enums\CategoryStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Market;
use App\Models\Product;
use App\Services\CategoryService;
use App\Traits\ZoneAvailability;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

#[Group('Categories')]
class CategoryApiController extends Controller
{
    use ZoneAvailability;

    /**
     * Get categories base api.
     * If slug is not provided, returns root categories.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of categories per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('slug', description: 'Category slug to filter by', type: 'string', example: 'apple')]
    #[QueryParameter('search', description: 'Search term to filter categories', type: 'string', example: 'electronics')]
    #[QueryParameter('include_no_product', description: 'When true, also return categories without product', type: 'boolean', example: 'false')]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'slug' => 'sometimes|string',
            'search' => 'sometimes|string',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $includeNoProduct = $request->input('include_no_product', false);
        $mainCategoryData = [];

        $query = Category::query()->with('parent')->where('status', CategoryStatusEnum::ACTIVE());
        if ($request->has('search')) {
            $query->where('title', 'like', '%'.$request->input('search').'%');
        }

        if ($request->has('slug')) {
            $parentCategory = Category::where('slug', $request->input('slug'))
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->first();
            if (! $parentCategory) {
                return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $this->emptyResponse($perPage, [
                    'main_category_data' => [],
                ]));
            }
            $mainCategoryData = [
                'id' => $parentCategory->id,
                'title' => $parentCategory->title,
            ];
            $query->where('parent_id', $parentCategory->id);
        } else {
            $query->whereNull('parent_id');
        }

        /** @var Market|null $market */
        $market = $request->attributes->get('market');
        [$useZoneFilter, $storeIds] = $this->marketContext($market);
        $this->applyProductsCount($query, $storeIds, $useZoneFilter);

        $allCategories = $query->orderBy('title')->get();
        $predicate = $request->has('slug')
            ? function (Category $cat) {
                return true;
            }
        : function (Category $cat) {
            return is_null($cat->parent_id);
        };

        $processed = $this->aggregateDescendantProducts($allCategories, $predicate, $useZoneFilter, $storeIds);

        if (! $includeNoProduct) {
            $processed = $this->filterNonZeroProducts($processed);
        }

        $paginator = $this->paginateCollection($processed, (int) $request->input('page', 1), $perPage, $request);
        $response = ApiResponseType::responseFromPaginator($paginator);
        if ($request->has('slug')) {
            $response['main_category_data'] = $mainCategoryData;
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $response);
    }

    /**
     * Get sub-categories.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of categories per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('filter', description: 'Filter enum: random | top_category', type: 'string', example: 'random')]
    public function subCategories(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'filter' => 'nullable|string',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $filter = $request->input('filter');

        $query = Category::query()
            ->with('parent')
            ->whereNotNull('parent_id')
            ->where('status', CategoryStatusEnum::ACTIVE());

        if ($filter === CategorySubCategoryFilterEnum::TOP_CATEGORY()) {
            $topCategory = Category::query()
                ->whereNull('parent_id')
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->first();
            if (! $topCategory) {
                return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $this->emptyResponse($perPage, ['filter' => $filter]));
            }
            $query->where('parent_id', $topCategory->id);
        }

        /** @var Market|null $market */
        $market = $request->attributes->get('market');
        [$useZoneFilter, $storeIds] = $this->marketContext($market);
        $this->applyProductsCount($query, $storeIds, $useZoneFilter);

        $filter === CategorySubCategoryFilterEnum::RANDOM()
            ? $query->inRandomOrder()
            : $query->orderBy('title');

        $allCategories = $query->get();

        $processed = $this->aggregateDescendantProducts($allCategories, function (Category $cat) {
            return ($cat->children_count ?? 0) > 0;
        }, $useZoneFilter, $storeIds);

        $filtered = $this->filterNonZeroProducts($processed);

        $paginator = $this->paginateCollection($filtered, (int) $request->input('page', 1), $perPage, $request);
        $response = array_merge(ApiResponseType::responseFromPaginator($paginator), ['filter' => $filter]);

        return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $response);
    }

    /**
     * Get categories for sidebar filter.
     */
    #[QueryParameter('ids', description: 'Comma separated list of category IDs or array of IDs', type: 'string', example: '1,2,3')]
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of categories per page', type: 'int', default: 15, example: 15)]
    public function getCategories(Request $request): JsonResponse
    {
        $idsInput = $request->input('ids');
        if (is_string($idsInput)) {
            $ids = array_filter(array_map('trim', explode(',', $idsInput)), fn ($v) => $v !== '');
            $request->merge(['ids' => $ids]);
        }

        $validated = $request->validate([
            'ids' => 'sometimes|array',
            'ids.*' => 'integer|min:1',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['ids'] ?? [])));

        $perPage = (int) $request->input('per_page', 15);

        $query = Category::query()
            ->with('parent')
            ->where('status', CategoryStatusEnum::ACTIVE());
        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        /** @var Market|null $market */
        $market = $request->attributes->get('market');
        [$useZoneFilter, $storeIds] = $this->marketContext($market);
        $this->applyProductsCount($query, $storeIds, $useZoneFilter);

        if (! empty($ids)) {
            $allCategories = $query->get()->sortBy(function (Category $cat) use ($ids) {
                return array_search($cat->id, $ids);
            })->values();
        } else {
            $allCategories = $query->orderBy('title')->get();
        }

        $processed = $this->aggregateDescendantProducts(
            $allCategories,
            function (Category $cat) {
                return is_null($cat->parent_id);
            },
            $useZoneFilter,
            $storeIds
        );

        $filtered = $this->filterNonZeroProducts($processed);

        $paginator = $this->paginateCollection($filtered, (int) $request->input('page', 1), $perPage, $request);
        $response = ApiResponseType::responseFromPaginator($paginator);

        return ApiResponseType::sendJsonResponse(true, 'labels.category_fetched_successfully', $response);
    }

    /**
     * Build an empty paginated-like response.
     */
    private function emptyResponse(int $perPage, array $extra = []): array
    {
        return array_merge([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
            'data' => [],
        ], $extra);
    }

    /**
     * Resolve the request's market and the store IDs enabled in it.
     * Returns [useMarketFilter, storeIds]. When no market is bound (admin / CLI),
     * the filter is off and counts revert to the global "all products" path.
     *
     * @return array{0: bool, 1: array<int>}
     */
    private function marketContext(?Market $market): array
    {
        if (! $market instanceof Market) {
            return [false, []];
        }

        $storeIds = Product::getStoreIdsForMarket($market);

        return [true, $storeIds];
    }

    /**
     * Apply children and products_count withCount to a query.
     */
    private function applyProductsCount(Builder $query, array $storeIds, bool $useZoneFilter = false): void
    {
        if ($useZoneFilter) {
            $query->withCount([
                'children',
                'products as products_count' => function ($q) use ($storeIds) {
                    if (empty($storeIds)) {
                        $q->whereRaw('1 = 0');

                        return;
                    }
                    $q->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                        ->where('status', ProductStatusEnum::ACTIVE())
                        ->whereHas('variants.storeProductVariants', function ($sq) use ($storeIds) {
                            $sq->whereIn('store_id', $storeIds);
                        });
                },
            ]);

            return;
        }
        $query->withCount(['children', 'products']);
    }

    /**
     * Recursively collect all active descendant category IDs below the given parent IDs.
     * The $visited set (seeded with the root category's own ID) prevents infinite loops
     * caused by accidental self-referencing or cyclic parent_id values in the data.
     *
     * @param  int[]  $parentIds  IDs whose children to fetch next
     * @param  int[]  $visited  IDs already seen — skip any child found here
     * @return int[]
     */
    private function collectAllDescendantIds(array $parentIds, array $visited = []): array
    {
        if (empty($parentIds)) {
            return [];
        }

        $childIds = Category::whereIn('parent_id', $parentIds)
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->pluck('id')
            ->toArray();

        $newIds = array_values(array_diff($childIds, $visited));

        if (empty($newIds)) {
            return [];
        }

        $updatedVisited = array_merge($visited, $newIds);

        return array_merge($newIds, $this->collectAllDescendantIds($newIds, $updatedVisited));
    }

    /**
     * For each category in the collection, add the full subtree product count when predicate matches.
     * Counts products from the category itself (already in products_count) plus ALL descendants.
     *
     * @param  Collection<int,Category>  $categories
     * @param  callable  $predicate  receives Category and returns bool to decide aggregation
     */
    private function aggregateDescendantProducts(Collection $categories, callable $predicate, bool $useZoneFilter, array $storeIds): Collection
    {
        return $categories->map(function (Category $cat) use ($predicate, $useZoneFilter, $storeIds) {
            if (! $predicate($cat)) {
                return $cat;
            }

            $allDescendantIds = $this->collectAllDescendantIds([$cat->id], [$cat->id]);

            if (empty($allDescendantIds)) {
                return $cat;
            }

            if ($useZoneFilter) {
                $additionalCount = Product::whereIn('category_id', $allDescendantIds)
                    ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->where('status', ProductStatusEnum::ACTIVE())
                    ->whereHas('variants.storeProductVariants', function ($sq) use ($storeIds) {
                        $sq->whereIn('store_id', $storeIds);
                    })
                    ->count();
            } else {
                $additionalCount = Product::whereIn('category_id', $allDescendantIds)->count();
            }

            $cat->products_count = ($cat->products_count ?? 0) + $additionalCount;

            return $cat;
        });
    }

    /**
     * Keep categories with products_count > 0.
     *
     * @param  Collection<int,Category>  $categories
     */
    private function filterNonZeroProducts(Collection $categories): Collection
    {
        return $categories->filter(function ($cat) {
            return ($cat->products_count ?? 0) > 0;
        })->values();
    }

    /**
     * Paginate an in-memory collection and wrap items in CategoryResource.
     */
    private function paginateCollection(Collection $categories, int $page, int $perPage, Request $request): LengthAwarePaginator
    {
        $total = $categories->count();
        $items = $categories->slice(($page - 1) * $perPage, $perPage)->values();
        $resourceItems = $items->map(fn ($cat) => new CategoryResource($cat));

        $paginator = new LengthAwarePaginator(
            $resourceItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
        $paginator->appends($request->query());

        return $paginator;
    }

    /**
     * Search categories with zone.
     */
    #[QueryParameter('search', description: 'Search term to filter categories by name or description', type: 'string', example: 'electronics')]
    #[QueryParameter('per_page', description: 'Categories Per Page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('zone_ids', description: 'Comma-separated list of zone IDs to filter categories', type: 'string', example: '1,2,3')]
    public function search(Request $request): JsonResponse
    {
        return $this->zoneSearch(
            request: $request,
            fetchPaginator: fn ($zoneIds, $search, $perPage) => app(CategoryService::class)->getAvailableByZoneIds($zoneIds, $search, $perPage),
            mapItem: fn ($item) => [
                'id' => $item->id,
                'value' => $item->id,
                'text' => $item->title,
                'image' => $item->image,
            ],
            successMessage: 'labels.categories_fetched_successfully',
            errorMessage: 'labels.error_fetching_categories',
        );
    }
}
