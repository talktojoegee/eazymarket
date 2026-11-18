<?php

namespace App\Http\Controllers\Api;

use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Market;
use App\Models\Product;
use App\Services\BrandService;
use App\Traits\ZoneAvailability;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
#[Group('Brands')]
class BrandApiController extends Controller
{
    use ZoneAvailability;

    public function __construct(protected BrandService $brandService) {}
    /**
     * Get brands with pagination and only active status.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of brands per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('ids', description: 'Comma separated list of brand IDs or array of IDs', type: 'string', example: '1,2,3')]
    #[QueryParameter('scope_category_slug', description: 'If you pass a category slug then brands will be filtered by category scope', type: 'string', example: "dairy")]
    public function index(Request $request): JsonResponse
    {
        $idsInput = $request->input('ids');
        if (is_string($idsInput)) {
            $ids = array_filter(array_map('trim', explode(',', $idsInput)), fn($v) => $v !== '');
            $request->merge(['ids' => $ids]);
        }

        $request->validate([
            'ids' => 'sometimes|array',
            'ids.*' => 'integer|min:1',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'scope_category_slug' => 'sometimes|string|nullable',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $perPage = $request->input('per_page', 15);
        $ids = array_values(array_unique(array_map('intval', $request->input('ids', []) ?? [])));

        $query = $this->buildMarketAwareBrandQuery($request);

        if ($request->has('scope_category_slug')) {
            $categorySlug = $request->input('scope_category_slug');
            $category = Category::where('slug', $categorySlug)->first();
            $query = Brand::scopeByCategory($query, $category->id ?? null);
        } else {
            $query->where('scope_type', HomePageScopeEnum::GLOBAL());
        }
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        if (!empty($ids)) {
            $idsList = implode(',', $ids);
            $query->orderByRaw("FIELD(id, $idsList)");
        } else {
            $query->orderBy('title');
        }

        $brands = $query->paginate($perPage);
        $brands->getCollection()->transform(fn($brand) => new BrandResource($brand));
        $response = [
            'current_page' => $brands->currentPage(),
            'last_page' => $brands->lastPage(),
            'per_page' => $brands->perPage(),
            'total' => $brands->total(),
            'data' => $brands->items(),
        ];
        return ApiResponseType::sendJsonResponse(true, 'labels.brand_fetched_successfully', $response);
    }

    /**
     * Build the base brand query for the customer's market.
     *
     * With a resolved market we delegate to BrandService::availableInStoresQuery()
     * over the market-enabled store set — same shape used by admin previews so
     * any tweak applies in one place. Without a market (admin / CLI) we keep
     * the unrestricted product count.
     */
    private function buildMarketAwareBrandQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        /** @var Market|null $market */
        $market = $request->attributes->get('market');

        if ($market instanceof Market) {
            $storeIds = Product::getStoreIdsForMarket($market);

            return $this->brandService->availableInStoresQuery($storeIds);
        }

        return Brand::query()
            ->where('status', BrandStatusEnum::ACTIVE())
            ->withCount('products')
            ->having('products_count', '>', 0);
    }

    /**
     * Get brands for sidebar.
     * @param Request $request
     * @return JsonResponse
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of brands per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('ids', description: 'Comma separated list of brand IDs or array of IDs', type: 'string', example: '1,2,3')]
    public function sidebarBrands(Request $request): JsonResponse
    {
        $idsInput = $request->input('ids');
        if (is_string($idsInput)) {
            $ids = array_filter(array_map('trim', explode(',', $idsInput)), fn($v) => $v !== '');
            $request->merge(['ids' => $ids]);
        }

        $request->validate([
            'ids' => 'sometimes|array',
            'ids.*' => 'integer|min:1',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $perPage = $request->input('per_page', 15);
        $ids = array_values(array_unique(array_map('intval', $request->input('ids', []) ?? [])));

        $query = $this->buildMarketAwareBrandQuery($request);

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        if (!empty($ids)) {
            $idsList = implode(',', $ids);
            $query->orderByRaw("FIELD(id, $idsList)");
        } else {
            $query->orderBy('title');
        }

        $brands = $query->paginate($perPage);
        $brands->getCollection()->transform(fn($brand) => new BrandResource($brand));
        $response = [
            'current_page' => $brands->currentPage(),
            'last_page' => $brands->lastPage(),
            'per_page' => $brands->perPage(),
            'total' => $brands->total(),
            'data' => $brands->items(),
        ];
        return ApiResponseType::sendJsonResponse(true, 'labels.brand_fetched_successfully', $response);
    }

    /**
     * Search brands with zone.
     */
    #[QueryParameter('search', description: 'Search term to filter brands by name or description', type: 'string', example: 'dairy')]
    #[QueryParameter('per_page', description: 'Brands Per Page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('zone_ids', description: 'Comma-separated list of zone IDs to filter brands', type: 'string', example: '1,2,3')]
    public function search(Request $request): JsonResponse
    {
        return $this->zoneSearch(
            request:        $request,
            fetchPaginator: fn($zoneIds, $search, $perPage) =>
            app(BrandService::class)->getAvailableByZoneIds($zoneIds, $search, $perPage),
            mapItem:        fn($item) => [
                'id'    => $item->id,
                'value' => $item->id,
                'text'  => $item->title,
                'image' => $item->logo,
            ],
            successMessage: 'labels.brands_fetched_successfully',
            errorMessage:   'labels.error_fetching_brands',
        );
    }
}

