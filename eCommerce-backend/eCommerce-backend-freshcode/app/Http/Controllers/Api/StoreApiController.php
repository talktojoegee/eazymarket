<?php

namespace App\Http\Controllers\Api;

use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\GetStoreByLocation;
use App\Http\Resources\StoreResource;
use App\Models\Market;
use App\Models\Store;
use App\Services\StoreService;
use App\Traits\ZoneAvailability;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Stores')]
class StoreApiController extends Controller
{
    use ZoneAvailability;
    /**
     * Stores visible in the customer's market.
     *
     * Endpoint name `getStoresByLocation` is preserved so mobile builds continue
     * routing to it. Semantically the gate is now market-based (store_market
     * pivot): a customer's market determines which stores are listed. Latitude
     * and longitude are accepted but ignored.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'int', default: 15, example: 15)]
    #[QueryParameter('search', description: 'Search term to filter store by name, description, address, or seller name', type: 'string', example: 'Beauty Essentials')]
    #[QueryParameter('recommended', description: 'Filter only recommended stores', type: 'boolean', example: true)]
    public function getStoresByLocation(GetStoreByLocation $request): JsonResponse
    {
        $validated = $request->validated();

        $search = $validated['search'] ?? '';
        $perPage = $validated['per_page'] ?? 15;
        $recommended = array_key_exists('recommended', $validated)
            ? filter_var($validated['recommended'], FILTER_VALIDATE_BOOLEAN)
            : null;

        /** @var Market|null $market */
        $market = $request->attributes->get('market');

        $query = Store::query()
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
            ->when($market, fn ($q) => $q
                ->whereHas('markets', fn ($m) => $m->where('markets.id', $market->id))
                ->whereHas('productVariants.productVariant.product', fn ($pq) => $pq
                    ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->where('status', ProductStatusEnum::ACTIVE())
                )
            )
            ->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('seller.user', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($recommended) {
            $query->where('is_recommended', true);
        }

        $stores = $query->paginate($perPage);

        $stores->getCollection()->transform(fn ($store) => new StoreResource($store));

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.stores_fetched_successfully'),
            data: $stores
        );
    }

    /**
     * @deprecated Phase-2 ecommerce — `delivery_zones` table is dropped. This
     * endpoint is retained as a stub that delegates to the market-scoped list
     * so old mobile builds don't crash. The `zone_id` parameter is ignored.
     */
    #[QueryParameter('zone_id', description: '(deprecated, ignored) Pre-migration delivery zone ID.', type: 'int', example: 1)]
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'int', default: 15, example: 15)]
    public function getStoresByZone(Request $request): JsonResponse
    {
        $request->validate([
            'zone_id'  => 'sometimes|integer',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page'     => 'sometimes|integer|min:1',
        ]);

        $perPage = $request->input('per_page', 15);

        /** @var Market|null $market */
        $market = $request->attributes->get('market');

        $query = Store::query()
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
            ->when($market, fn ($q) => $q->whereHas('markets', fn ($m) => $m->where('markets.id', $market->id)))
            ->orderBy('name');

        $stores = $query->paginate($perPage);
        $stores->getCollection()->transform(fn ($store) => new StoreResource($store));

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.stores_fetched_successfully'),
            data: $stores
        );
    }

    /**
     * Get Store by Slug. latitude / longitude accepted-but-ignored for back-compat.
     */
    public function show($slug, Request $request): JsonResponse
    {
        /** @var Market|null $market */
        $market = $request->attributes->get('market');

        $query = Store::where('slug', $slug)
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE());

        if ($market) {
            $query->whereHas('markets', fn ($q) => $q->where('markets.id', $market->id))
                ->whereHas('productVariants.productVariant.product', fn ($pq) => $pq
                    ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->where('status', ProductStatusEnum::ACTIVE())
                );
        }

        $store = $query->first();

        if (!$store) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.store_not_found_with_slug'),
                data: []
            );
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.store_fetched_successfully'),
            data: new StoreResource($store)
        );
    }

    /**
     * Get all active stores with pagination.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'int', default: 15, example: 15)]
    #[QueryParameter('recommended', description: 'Filter only recommended stores', type: 'boolean', example: true)]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $recommended = $request->has('recommended')
            ? filter_var($request->input('recommended'), FILTER_VALIDATE_BOOLEAN)
            : null;

        /** @var Market|null $market */
        $market = $request->attributes->get('market');

        $query = Store::query()
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
            ->when($market, fn ($q) => $q
                ->whereHas('markets', fn ($m) => $m->where('markets.id', $market->id))
                ->whereHas('productVariants.productVariant.product', fn ($pq) => $pq
                    ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->where('status', ProductStatusEnum::ACTIVE())
                )
            )
            ->orderBy('name');

        if ($recommended) {
            $query->where('is_recommended', true);
        }

        $stores = $query->paginate($perPage);
        $stores->getCollection()->transform(fn($store) => new StoreResource($store));

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.stores_fetched_successfully'),
            data: $stores,
        );
    }

    /**
     * Search stores with zone.
     */
    #[QueryParameter('search', description: 'Search term to filter stores by name, description, address, or seller name', type: 'string', example: 'Beauty Store')]
    #[QueryParameter('per_page', description: 'Stores Per Page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('zone_ids', description: 'Comma-separated list of zone IDs to filter stores', type: 'string', example: '1,2,3')]
    public function search(Request $request): JsonResponse
    {
        return $this->zoneSearch(
            request:        $request,
            fetchPaginator: fn($zoneIds, $search, $perPage) =>
            app(StoreService::class)->getAvailableStoresByZoneIds(
                zoneIds: $zoneIds, search: $search, perPage: $perPage
            ),
            mapItem:        fn($item) => [
                'id'    => $item->id,
                'value' => $item->id,
                'text'  => $item->name,
                'image' => $item->store_logo,
            ],
            successMessage: __('labels.stores_fetched_successfully'),
            errorMessage:   __('labels.error_fetching_stores'),
        );
    }
}
