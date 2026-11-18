<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductTypeEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Events\Store\StoreConfigUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreStoreConfigurationRequest;
use App\Http\Requests\Store\StoreStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Models\ProductVariant;
use App\Services\KitStoreAvailabilityService;
use App\Services\SellerStoreService;
use App\Services\StoreService;
use App\Types\Api\ApiResponseType;
use App\Models\Setting;
use App\Services\SubscriptionUsageService;
use App\Traits\SubscriptionLimitGuard;
use App\Enums\Subscription\SubscriptionPlanKeyEnum;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

#[Group('Seller Stores')]
class SellerStoreApiController extends Controller
{
    use AuthorizesRequests, SubscriptionLimitGuard;

    public function __construct(protected StoreService $storeService)
    {
    }

    /**
     * List stores for the authenticated seller with pagination and search
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of stores per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('search', description: 'Search query for name/description/city/contact number', type: 'string', example: 'Downtown')]
    #[QueryParameter('visibility_status', description: 'Filter by store visibility status', type: 'string', example: 'visible/draft')]
    #[QueryParameter('verification_status', description: 'Filter by store verification status', type: 'string', example: 'approved/not_approved')]
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Store::class);

            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }
            $perPage = (int) $request->input('per_page', 15);
            $q = trim((string) $request->input('search', ''));

            $filters = $request->only(['visibility_status', 'verification_status']);

            $paginator = $this->storeService->listForSeller($seller, $q, $perPage, $filters);
            $paginator->getCollection()->transform(fn ($store) => new StoreResource($store));

            $response = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'data' => $paginator->items(),
            ];

            return ApiResponseType::sendJsonResponse(true, __('labels.stores_fetched_successfully'), $response);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            Log::error('Seller stores index error: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(false, __('labels.error_fetching_stores'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show a single store owned by the authenticated seller
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('view', $store);

            return ApiResponseType::sendJsonResponse(true, __('labels.store_fetched_successfully'), new StoreResource($store->load('markets:id,code,name')));
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.store_not_found') ?? 'Store not found', [], 404);
        }
    }

    /**
     * Create a new store for seller
     */
    public function store(StoreStoreRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Store::class);
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }
            if ($error = $this->ensureCanUseOrError($seller->id, SubscriptionPlanKeyEnum::STORE_LIMIT())) {
                return $error;
            }
            $store = $this->storeService->createForSeller($request, $seller);

            $this->recordUsageIfMultivendor($seller->id, SubscriptionPlanKeyEnum::STORE_LIMIT());
            return ApiResponseType::sendJsonResponse(true, __('labels.store_created_successfully') ?? 'Store created successfully', new StoreResource($store));
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.error_creating_store') ?? $e->getMessage(), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an owned store
     */
    public function update(UpdateStoreRequest $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }
            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('update', $store);

            $store = $this->storeService->updateForSeller($request, $store);
            return ApiResponseType::sendJsonResponse(true, __('labels.store_updated_successfully') ?? 'Store updated successfully', new StoreResource($store));
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.error_updating_store') ?? $e->getMessage(), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete an owned store
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }
            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('delete', $store);

            $blockers = $this->storeService->getDeleteBlockers($store);
            if (!empty($blockers)) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    __('labels.store_delete_blocked', ['items' => implode(', ', $blockers)]),
                    [],
                    422
                );
            }

            $this->storeService->deleteForSeller($store);

            $this->reduceUsageIfMultivendor($seller->id, SubscriptionPlanKeyEnum::STORE_LIMIT());
            return ApiResponseType::sendJsonResponse(true, __('labels.store_deleted_successfully') ?? 'Store deleted successfully', []);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.error_deleting_store') ?? $e->getMessage(), ['error' => $e->getMessage()], 500);
        }
    }



    /**
     * The store the current request is scoped to, plus every store the seller
     * can switch to. The client sends the chosen id back as the `X-Store-Id`
     * header on all later calls — the seller API keeps no server-side selection.
     */
    public function context(Request $request, SellerStoreService $stores): JsonResponse
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $current = $request->attributes->get('seller_store');

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.stores_fetched_successfully'),
            [
                'current_store' => $current ? new StoreResource($current) : null,
                'stores' => StoreResource::collection($stores->storesForCurrentSeller()->load('markets:id,code,name')),
            ]
        );
    }

    /**
     * Validate a store the seller wants to scope to. Returns the store so the
     * client can pin it as `X-Store-Id`; a store the seller does not own is
     * rejected with 403.
     */
    public function switchStore(Request $request): JsonResponse
    {
        $request->validate(['store_id' => 'required|integer']);

        $seller = auth()->user()?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $store = app(SellerStoreService::class)->useStore((int) $request->input('store_id'));
        if (!$store) {
            return ApiResponseType::sendJsonResponse(false, __('labels.store_not_found'), [], 403);
        }

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.store_fetched_successfully'),
            new StoreResource($store->load('markets:id,code,name'))
        );
    }

    /**
     * Flat id/name list of the seller's stores for pickers.
     */
    public function list(SellerStoreService $scope): JsonResponse
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $stores = $seller->stores()
            ->whereIn('id', $scope->storesForCurrentSeller()->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'contact_number', 'verification_status', 'visibility_status']);

        return ApiResponseType::sendJsonResponse(true, __('labels.stores_fetched_successfully'), $stores);
    }

    /**
     * Seller's stores with their active locations nested, for the inventory grid.
     */
    public function withLocations(Request $request, SellerStoreService $scope, KitStoreAvailabilityService $kitAvailability): JsonResponse
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $validated = $request->validate([
            'component_variant_ids' => ['sometimes', 'array', 'min:1', 'max:30'],
            'component_variant_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $componentVariantIds = array_map('intval', $validated['component_variant_ids'] ?? []);
        $eligibleStoreIds = null;
        if ($componentVariantIds !== []) {
            $validComponentCount = ProductVariant::query()
                ->whereIn('id', $componentVariantIds)
                ->whereHas('product', fn ($query) => $query
                    ->where('seller_id', $seller->id)
                    ->where('status', ProductStatusEnum::ACTIVE())
                    ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->whereDoesntHave('comboPack'))
                ->distinct()
                ->count('id');
            if ($validComponentCount !== count(array_unique($componentVariantIds))) {
                return ApiResponseType::sendJsonResponse(false, __('labels.combo_pack_component_unavailable'), ['errors' => ['component_variant_ids' => [__('labels.combo_pack_component_unavailable')]]], 422);
            }
            $eligibleStoreIds = $kitAvailability->eligibleStoreIds(
                $componentVariantIds,
                (int) $seller->id,
                $scope->accessibleStoreIds(),
            );
        }

        $stores = Store::query()
            ->where('seller_id', $seller->id)
            ->whereIn('id', $scope->storesForCurrentSeller()->pluck('id'))
            ->when($eligibleStoreIds !== null, fn ($query) => $query->whereIn('id', $eligibleStoreIds))
            ->with(['locations' => function ($q) {
                $q->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->select(['id', 'store_id', 'name', 'is_default', 'is_active']);
            }])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($store) => [
                'id' => $store->id,
                'name' => $store->name,
                'locations' => $store->locations->map(fn ($l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'is_default' => (bool) $l->is_default,
                    'is_active' => (bool) $l->is_active,
                ])->values(),
            ]);

        return ApiResponseType::sendJsonResponse(true, __('labels.stores_fetched_successfully'), $stores);
    }

    /**
     * Current storefront configuration (policies, SEO metadata, POS payment config).
     */
    public function configuration(int $id): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('view', $store);

            return ApiResponseType::sendJsonResponse(true, __('labels.store_fetched_successfully'), [
                'id' => $store->id,
                'description' => $store->description,
                'about_us' => $store->about_us,
                'promotional_text' => $store->promotional_text,
                'return_replacement_policy' => $store->return_replacement_policy,
                'refund_policy' => $store->refund_policy,
                'terms_and_conditions' => $store->terms_and_conditions,
                'metadata' => $store->metadataWithSeo(),
                'pos_upi_vpa' => $store->pos_upi_vpa,
                'pos_upi_payee_name' => $store->pos_upi_payee_name,
                'receipt_template' => $store->receipt_template,
                'pos_payment_config' => $store->pos_payment_config,
            ]);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.store_not_found'), [], 404);
        }
    }

    /**
     * Update the storefront configuration of an owned store.
     */
    public function updateConfiguration(StoreStoreConfigurationRequest $request, int $id): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $store = $this->storeService->findOwnedOrFail($seller, $id);
            $this->authorize('update', $store);

            $store->update($request->validated());
            event(new StoreConfigUpdated($store));

            return ApiResponseType::sendJsonResponse(true, __('labels.store_updated_successfully'), new StoreResource($store));
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Get all store enums
     * @return JsonResponse
     */
    public function getStoresEnums(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'stores enums fetched successfully',
            data: [
                'visibility_status' => StoreVisibilityStatusEnum::values(),
                'verification_status' => StoreVerificationStatusEnum::values()
            ]
        );
    }
}
