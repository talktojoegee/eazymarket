<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Requests\StoreLocation\StoreLocationStoreRequest;
use App\Http\Requests\StoreLocation\UpdateStoreLocationRequest;
use App\Jobs\SyncShiprocketPickupLocation;
use App\Models\Seller;
use App\Models\StoreLocation;
use App\Services\Shipping\ShiprocketClient;
use App\Services\StoreLocationService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ship-from locations for the seller app — the API analog of the seller panel's
 * cross-store `seller/locations` screens. Every lookup is bounded to the
 * seller's own stores, and the list defaults to the store the request is scoped
 * to via `X-Store-Id`.
 */
#[Group('Seller Store Locations')]
class SellerStoreLocationApiController
{
    use ChecksPermissions;

    public function __construct(protected StoreLocationService $service)
    {
    }

    /**
     * A seller staff user needs the matching store_location permission; the
     * seller account itself (SELLER role) always passes.
     */
    protected function ensurePermission(string $permission): ?JsonResponse
    {
        $user = auth()->user();
        if ($user?->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission)) {
            return null;
        }

        return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
    }

    /**
     * Locations of the scoped store, or of every store the seller owns when
     * `all=1` is passed.
     */
    #[QueryParameter('all', description: 'Return locations across all of the seller\'s stores instead of the scoped one', type: 'bool', default: false)]
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->ensurePermission(SellerPermissionEnum::STORE_LOCATION_VIEW())) {
            return $denied;
        }

        try {
            $seller = $this->requireSeller();
            $storeIds = $seller->stores()->pluck('id');
            $scopedStore = $request->attributes->get('seller_store');

            $locations = StoreLocation::query()
                ->with('store:id,name,slug')
                ->whereIn('store_id', $storeIds)
                ->when(
                    !$request->boolean('all') && $scopedStore,
                    fn ($q) => $q->where('store_id', $scopedStore->id)
                )
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();

            return ApiResponseType::sendJsonResponse(true, 'labels.locations_fetched_successfully', $locations);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', [], 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * A single location owned by the seller.
     */
    public function show(int $id): JsonResponse
    {
        if ($denied = $this->ensurePermission(SellerPermissionEnum::STORE_LOCATION_VIEW())) {
            return $denied;
        }

        try {
            $location = $this->findOwned($id);

            return ApiResponseType::sendJsonResponse(true, 'labels.location_fetched_successfully', $location);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
        }
    }

    /**
     * Create a location. The target store comes from `store_id`, falling back to
     * the store the request is scoped to.
     */
    public function store(StoreLocationStoreRequest $request): JsonResponse
    {
        try {
            if ($denied = $this->ensurePermission(SellerPermissionEnum::STORE_LOCATION_CREATE())) {
                return $denied;
            }
            $seller = $this->requireSeller();
            $storeId = (int) ($request->input('store_id') ?: $request->attributes->get('seller_store')?->id);
            $store = $seller->stores()->findOrFail($storeId);

            $location = $this->service->create($store, $request->validated());

            return ApiResponseType::sendJsonResponse(true, 'labels.location_created', $location, 201);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Update a location. A location cannot move between stores.
     */
    public function update(UpdateStoreLocationRequest $request, int $id): JsonResponse
    {
        try {
            if ($denied = $this->ensurePermission(SellerPermissionEnum::STORE_LOCATION_EDIT())) {
                return $denied;
            }
            $location = $this->service->update($this->findOwned($id), $request->validated());

            return ApiResponseType::sendJsonResponse(true, 'labels.location_updated', $location);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Delete a location. The store's default location cannot be deleted —
     * promote another one first.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            if ($denied = $this->ensurePermission(SellerPermissionEnum::STORE_LOCATION_DELETE())) {
                return $denied;
            }
            $this->service->delete($this->findOwned($id));

            return ApiResponseType::sendJsonResponse(true, 'labels.location_deleted', []);
        } catch (\DomainException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.cannot_delete_default_location', [], 422);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Make this location the store's default ship-from point.
     */
    public function promoteDefault(int $id): JsonResponse
    {
        try {
            if ($denied = $this->ensurePermission(SellerPermissionEnum::STORE_LOCATION_EDIT())) {
                return $denied;
            }
            $location = $this->service->promoteToDefault($this->findOwned($id));

            return ApiResponseType::sendJsonResponse(true, 'labels.location_promoted', $location);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Register / re-register the location as a Shiprocket pickup location.
     */
    public function shiprocketSync(int $id): JsonResponse
    {
        try {
            if ($denied = $this->ensurePermission(SellerPermissionEnum::STORE_LOCATION_EDIT())) {
                return $denied;
            }
            $location = $this->findOwned($id);

            if (!app(ShiprocketClient::class)->isEnabled()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.shiprocket_disabled', [], 422);
            }

            try {
                SyncShiprocketPickupLocation::dispatchSync($location->id, true);
            } catch (\Throwable $e) {
            }

            $location->refresh();

            if ($location->isShiprocketRegistered()) {
                return ApiResponseType::sendJsonResponse(
                    true,
                    'labels.shiprocket_registered_ok',
                    ['status' => $location->shiprocket_sync_status],
                );
            }

            return ApiResponseType::sendJsonResponse(
                false,
                $location->shiprocket_sync_error ?: __('labels.shiprocket_sync_failed_generic'),
                ['status' => $location->shiprocket_sync_status],
                422,
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 500);
        }
    }

    protected function requireSeller(): Seller
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            throw new ModelNotFoundException('Seller not found');
        }

        return $seller;
    }

    protected function findOwned(int $locationId): StoreLocation
    {
        return StoreLocation::whereIn('store_id', $this->requireSeller()->stores()->pluck('id'))
            ->findOrFail($locationId);
    }
}
