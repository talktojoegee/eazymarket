<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\Shipping\ShiprocketSyncStatusEnum;
use App\Http\Requests\StoreLocation\StoreLocationStoreRequest;
use App\Http\Requests\StoreLocation\UpdateStoreLocationRequest;
use App\Jobs\SyncShiprocketPickupLocation;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Services\SettingService;
use App\Services\Shipping\ShiprocketClient;
use App\Services\StoreLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * StoreLocation CRUD — panel-aware (admin + seller use the same controller).
 *
 * Routes nest under a Store: index / create / store / edit / update / destroy
 * + a dedicated promote-default endpoint that runs the atomic flip in the
 * service layer.
 */
class StoreLocationController extends Controller
{
    use PanelAware, AuthorizesRequests, ChecksPermissions;

    protected bool $viewPermission = true;
    protected bool $createPermission = true;
    protected bool $editPermission = true;
    protected bool $deletePermission = true;

    public function __construct(protected StoreLocationService $service)
    {
        $panel = $this->getPanel();
        $enum = $panel === 'admin' ? AdminPermissionEnum::class : SellerPermissionEnum::class;
        $user = auth()->user();

        $this->viewPermission   = $this->hasPermission($enum::STORE_LOCATION_VIEW()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        $this->createPermission = $this->hasPermission($enum::STORE_LOCATION_CREATE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        $this->editPermission   = $this->hasPermission($enum::STORE_LOCATION_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        $this->deletePermission = $this->hasPermission($enum::STORE_LOCATION_DELETE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
    }

    /**
     * Resolve the Google Maps JS API key (stored under authentication settings,
     * same source the stores / settings maps use). Returns null when unset so
     * the form can hide the search input gracefully.
     */
    protected function googleApiKey(): ?string
    {
        $settingService = app(SettingService::class);
        $resource = $settingService->getSettingByVariable('web');
        $webSettings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];
        return $webSettings['googleMapKey'] ?? null;
    }

    /**
     * GET /…/stores/{store}/locations — list cards
     */
    public function index($storeId): View
    {
        abort_unless($this->viewPermission, 403);
        $store = $this->resolveStore($storeId);
        $locations = $store->locations()->orderByDesc('is_default')->orderBy('name')->get();

        return view($this->panelView('store_locations.index'), [
            'store'            => $store,
            'locations'        => $locations,
            'createPermission' => $this->createPermission,
            'editPermission'   => $this->editPermission,
            'deletePermission' => $this->deletePermission,
        ]);
    }

    /**
     * GET /…/stores/{store}/locations/create
     */
    public function create($storeId): View
    {
        abort_unless($this->createPermission, 403);
        $store = $this->resolveStore($storeId);

        return view($this->panelView('store_locations.form'), [
            'store'        => $store,
            'location'     => new StoreLocation(['fulfills_online_orders' => true, 'is_active' => true]),
            'action'       => 'create',
            'googleApiKey' => $this->googleApiKey(),
        ]);
    }

    /**
     * POST /…/stores/{store}/locations
     */
    public function store(StoreLocationStoreRequest $request, $storeId): JsonResponse
    {
        abort_unless($this->createPermission, 403);
        try {
            $store = $this->resolveStore($storeId);
            $location = $this->service->create($store, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.location_created'),
                data: ['location' => $location, 'redirect' => $this->indexRoute($store)],
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * GET /…/stores/{store}/locations/{location}/edit
     */
    public function edit($storeId, $locationId): View
    {
        abort_unless($this->editPermission, 403);
        $store = $this->resolveStore($storeId);
        $location = $store->locations()->findOrFail($locationId);

        return view($this->panelView('store_locations.form'), [
            'store'        => $store,
            'location'     => $location,
            'action'       => 'edit',
            'googleApiKey' => $this->googleApiKey(),
        ]);
    }

    /**
     * POST /…/stores/{store}/locations/{location}
     */
    public function update(UpdateStoreLocationRequest $request, $storeId, $locationId): JsonResponse
    {
        abort_unless($this->editPermission, 403);
        try {
            $store = $this->resolveStore($storeId);
            $location = $store->locations()->findOrFail($locationId);
            $location = $this->service->update($location, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.location_updated'),
                data: ['location' => $location, 'redirect' => $this->indexRoute($store)],
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * DELETE /…/stores/{store}/locations/{location}
     *
     * The StoreLocationObserver throws a DomainException if the row is the
     * store's default — we surface that as a user-facing message rather than
     * a 500.
     */
    public function destroy($storeId, $locationId): JsonResponse
    {
        abort_unless($this->deletePermission, 403);
        try {
            $store = $this->resolveStore($storeId);
            $location = $store->locations()->findOrFail($locationId);
            $this->service->delete($location);

            return ApiResponseType::sendJsonResponse(true, __('labels.location_deleted'), []);
        } catch (\DomainException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.cannot_delete_default_location'), []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * POST /…/stores/{store}/locations/{location}/promote-default
     */
    public function promoteDefault($storeId, $locationId): JsonResponse
    {
        abort_unless($this->editPermission, 403);
        try {
            $store = $this->resolveStore($storeId);
            $location = $store->locations()->findOrFail($locationId);
            $location = $this->service->promoteToDefault($location);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.location_promoted'),
                data: ['location' => $location],
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * POST /…/stores/{store}/locations/{location}/shiprocket-sync
     */
    public function shiprocketSync($storeId, $locationId): JsonResponse
    {
        abort_unless($this->editPermission, 403);
        try {
            $store = $this->resolveStore($storeId);
            $location = $store->locations()->findOrFail($locationId);
            return $this->dispatchShiprocketSync($location);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * POST /seller/locations/{location}/shiprocket-sync
     */
    public function allShiprocketSync($locationId): JsonResponse
    {
        abort_unless($this->editPermission, 403);
        try {
            $seller = $this->requireSeller();
            $location = StoreLocation::whereIn('store_id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())
                ->findOrFail($locationId);
            return $this->dispatchShiprocketSync($location);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * GET admin/store-locations — cross-seller list with Shiprocket status.
     */
    public function adminGlobalIndex(Request $request): View
    {
        abort_unless($this->viewPermission, 403);
        $status = $request->query('sync_status');
        $search = trim((string) $request->query('q', ''));
        $shiprocketEnabled = (bool) (app(SettingService::class)->shiprocketConfig()['shiprocketEnabled'] ?? false);

        $locations = StoreLocation::query()
            ->with(['store:id,name,seller_id', 'store.seller.user:id,name'])
            ->when($status, fn ($q) => $q->where('shiprocket_sync_status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('pin_code', 'like', "%{$search}%")
                        ->orWhereHas('store', fn ($s) => $s->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.store_locations.global_index', [
            'locations'         => $locations,
            'status'            => $status,
            'search'            => $search,
            'shiprocketEnabled' => $shiprocketEnabled,
        ]);
    }

    private function dispatchShiprocketSync(StoreLocation $location): JsonResponse
    {
        if (!app(ShiprocketClient::class)->isEnabled()) {
            return ApiResponseType::sendJsonResponse(false, __('labels.shiprocket_disabled'), []);
        }

        try {
            SyncShiprocketPickupLocation::dispatchSync($location->id, true);
        } catch (\Throwable $e) {
            Log::error('Shiprocket pickup sync failed', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);
        }

        $location->refresh();

        if ($location->isShiprocketRegistered()) {
            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.shiprocket_registered_ok'),
                data: ['status' => $location->shiprocket_sync_status],
            );
        }

        return ApiResponseType::sendJsonResponse(
            success: false,
            message: $location->shiprocket_sync_error ?: __('labels.shiprocket_sync_failed_generic'),
            data: ['status' => $location->shiprocket_sync_status],
        );
    }


    /**
     * GET /seller/locations?store_id=X — cross-store list with store filter.
     */
    public function allIndex(Request $request): View
    {
        abort_unless($this->viewPermission, 403);
        $seller = $this->requireSeller();
        $stores = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->orderBy('name')->get();
        $selectedStoreId = app(\App\Services\SellerStoreService::class)->selectedId();

        $locations = StoreLocation::query()
            ->with('store:id,name,slug')
            ->whereIn('store_id', $stores->pluck('id'))
            ->when($selectedStoreId, fn ($q) => $q->where('store_id', $selectedStoreId))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('seller.locations.index', [
            'stores'           => $stores,
            'selectedStoreId'  => $selectedStoreId,
            'locations'        => $locations,
            'createPermission' => $this->createPermission,
            'editPermission'   => $this->editPermission,
            'deletePermission' => $this->deletePermission,
        ]);
    }

    /**
     * GET /seller/locations/create — form with a Store picker at the top.
     */
    public function allCreate(Request $request): View
    {
        $seller = $this->requireSeller();
        $stores = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->orderBy('name')->get();
        $preselectedStoreId = app(\App\Services\SellerStoreService::class)->selectedId() ?: $stores->first()?->id;

        return view('seller.locations.form', [
            'stores'       => $stores,
            'storeId'      => $preselectedStoreId,
            'location'     => new StoreLocation(['fulfills_online_orders' => true, 'is_active' => true]),
            'action'       => 'create',
            'googleApiKey' => $this->googleApiKey(),
        ]);
    }

    /**
     * POST /seller/locations
     */
    public function allStore(StoreLocationStoreRequest $request): JsonResponse
    {
        try {
            $seller = $this->requireSeller();
            $storeId = (int) $request->input('store_id');
            $store = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->findOrFail($storeId);

            $location = $this->service->create($store, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.location_created'),
                data: ['location' => $location, 'redirect' => route('seller.locations.index')],
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * GET /seller/locations/{location}/edit — store picker is read-only on
     * edit (a location can't move between stores; create a new one instead).
     */
    public function allEdit($locationId): View
    {
        $seller = $this->requireSeller();
        $location = StoreLocation::whereIn('store_id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())
            ->findOrFail($locationId);
        $stores = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->orderBy('name')->get();

        return view('seller.locations.form', [
            'stores'       => $stores,
            'storeId'      => $location->store_id,
            'location'     => $location,
            'action'       => 'edit',
            'googleApiKey' => $this->googleApiKey(),
        ]);
    }

    /**
     * POST /seller/locations/{location}
     */
    public function allUpdate(UpdateStoreLocationRequest $request, $locationId): JsonResponse
    {
        try {
            $seller = $this->requireSeller();
            $location = StoreLocation::whereIn('store_id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())
                ->findOrFail($locationId);

            $location = $this->service->update($location, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.location_updated'),
                data: ['location' => $location, 'redirect' => route('seller.locations.index')],
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * DELETE /seller/locations/{location}
     */
    public function allDestroy($locationId): JsonResponse
    {
        try {
            $seller = $this->requireSeller();
            $location = StoreLocation::whereIn('store_id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())
                ->findOrFail($locationId);
            $this->service->delete($location);

            return ApiResponseType::sendJsonResponse(true, __('labels.location_deleted'), []);
        } catch (\DomainException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.cannot_delete_default_location'), []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * POST /seller/locations/{location}/promote-default
     */
    public function allPromoteDefault($locationId): JsonResponse
    {
        try {
            $seller = $this->requireSeller();
            $location = StoreLocation::whereIn('store_id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())
                ->findOrFail($locationId);
            $location = $this->service->promoteToDefault($location);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.location_promoted'),
                data: ['location' => $location],
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * Ensure the request comes from a seller account; 404 if not.
     */
    protected function requireSeller(): Seller
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            throw new ModelNotFoundException('Seller not found');
        }
        return $seller;
    }

    /**
     * Resolve the store, scoping to the seller's own stores on the seller panel.
     */
    protected function resolveStore($storeId): Store
    {
        $store = Store::findOrFail($storeId);

        if ($this->getPanel() === 'seller') {
            $seller = auth()->user()?->seller();
            if (!$seller || (int) $store->seller_id !== (int) $seller->id) {
                throw new ModelNotFoundException('Store not found');
            }
        }

        return $store;
    }

    /**
     * Build the redirect target for the post-save flow.
     */
    protected function indexRoute(Store $store): string
    {
        return $this->getPanel() === 'admin'
            ? route('admin.sellers.store.locations.index', ['storeId' => $store->id])
            : route('seller.stores.locations.index', ['storeId' => $store->id]);
    }
}
