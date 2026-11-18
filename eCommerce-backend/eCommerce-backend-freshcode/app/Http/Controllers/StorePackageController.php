<?php

namespace App\Http\Controllers;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Requests\StorePackage\StoreStorePackageRequest;
use App\Http\Requests\StorePackage\UpdateStorePackageRequest;
use App\Models\Seller;
use App\Models\StorePackage;
use App\Services\StorePackageService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * StorePackage CRUD — Shopify-style "Settings → Shipping and delivery → Packages".
 *
 * Phase 1 (this file): seller cross-store CRUD with a store filter, mirrors
 * the StoreLocationController / ShippingProfileController allXxx pattern.
 * The auto-seeded default package ("Default box") cannot be deleted — observer
 * enforces this and the index hides the trash button.
 */
class StorePackageController extends Controller
{
    use AuthorizesRequests, ChecksPermissions;

    protected bool $viewPermission = true;
    protected bool $createPermission = true;
    protected bool $editPermission = true;
    protected bool $deletePermission = true;

    public function __construct(protected StorePackageService $service)
    {
        $user = auth()->user();
        $this->viewPermission   = $this->hasPermission(SellerPermissionEnum::SHIPPING_PACKAGE_VIEW())   || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        $this->createPermission = $this->hasPermission(SellerPermissionEnum::SHIPPING_PACKAGE_CREATE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        $this->editPermission   = $this->hasPermission(SellerPermissionEnum::SHIPPING_PACKAGE_EDIT())   || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        $this->deletePermission = $this->hasPermission(SellerPermissionEnum::SHIPPING_PACKAGE_DELETE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
    }

    /**
     * GET /seller/shipping/packages?store_id=X — cross-store list.
     */
    public function index(Request $request): View
    {
        abort_unless($this->viewPermission, 403);
        $seller = $this->requireSeller();
        $stores = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->orderBy('name')->get();
        $selectedStoreId = app(\App\Services\SellerStoreService::class)->selectedId();

        $packages = StorePackage::query()
            ->with('store:id,name,slug')
            ->whereIn('store_id', $stores->pluck('id'))
            ->when($selectedStoreId, fn ($q) => $q->where('store_id', $selectedStoreId))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('seller.shipping.packages.index', [
            'stores'           => $stores,
            'selectedStoreId'  => $selectedStoreId,
            'packages'         => $packages,
            'createPermission' => $this->createPermission,
            'editPermission'   => $this->editPermission,
            'deletePermission' => $this->deletePermission,
        ]);
    }

    /**
     * GET /seller/shipping/packages/create — form with a Store picker at the top.
     */
    public function create(Request $request): View
    {
        abort_unless($this->viewPermission, 403);
        $seller = $this->requireSeller();
        $stores = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->orderBy('name')->get();
        $preselectedStoreId = app(\App\Services\SellerStoreService::class)->selectedId() ?: $stores->first()?->id;

        return view('seller.shipping.packages.form', [
            'stores'  => $stores,
            'storeId' => $preselectedStoreId,
            'package' => new StorePackage(['type' => StorePackage::TYPE_BOX]),
            'action'  => 'create',
        ]);
    }

    /**
     * POST /seller/shipping/packages
     */
    public function store(StoreStorePackageRequest $request): JsonResponse
    {
        try {
            if (!$this->createPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $seller = $this->requireSeller();
            $storeId = (int) $request->input('store_id');
            $store = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->findOrFail($storeId);

            $package = $this->service->create($store, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.store_package_created'),
                data: ['package' => $package, 'redirect' => route('seller.shipping.packages.index')],
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * GET /seller/shipping/packages/{package}/edit — store locked on edit.
     */
    public function edit($packageId): View
    {
        abort_unless($this->viewPermission, 403);
        $seller = $this->requireSeller();
        $package = $this->resolvePackage($seller, $packageId);
        $stores = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->orderBy('name')->get();

        return view('seller.shipping.packages.form', [
            'stores'  => $stores,
            'storeId' => $package->store_id,
            'package' => $package,
            'action'  => 'edit',
        ]);
    }

    /**
     * POST /seller/shipping/packages/{package}
     */
    public function update(UpdateStorePackageRequest $request, $packageId): JsonResponse
    {
        try {
            if (!$this->editPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $seller = $this->requireSeller();
            $package = $this->resolvePackage($seller, $packageId);
            $package = $this->service->update($package, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.store_package_updated'),
                data: ['package' => $package, 'redirect' => route('seller.shipping.packages.index')],
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * DELETE /seller/shipping/packages/{package}
     */
    public function destroy($packageId): JsonResponse
    {
        try {
            if (!$this->deletePermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $seller = $this->requireSeller();
            $package = $this->resolvePackage($seller, $packageId);
            $this->service->delete($package);

            return ApiResponseType::sendJsonResponse(true, __('labels.store_package_deleted'), []);
        } catch (\DomainException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.cannot_delete_default_store_package'), []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * POST /seller/shipping/packages/{package}/promote-default
     */
    public function promoteDefault($packageId): JsonResponse
    {
        try {
            if (!$this->editPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $seller = $this->requireSeller();
            $package = $this->resolvePackage($seller, $packageId);
            $package = $this->service->promoteToDefault($package);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.store_package_promoted'),
                data: ['package' => $package],
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
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

    protected function resolvePackage(Seller $seller, $packageId): StorePackage
    {
        return StorePackage::whereIn('store_id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())
            ->findOrFail($packageId);
    }
}
