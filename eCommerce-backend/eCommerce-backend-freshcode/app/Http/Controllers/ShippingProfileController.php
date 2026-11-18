<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Requests\ShippingProfile\StoreShippingProfileRequest;
use App\Http\Requests\ShippingProfile\UpdateShippingProfileRequest;
use App\Models\Product;
use App\Models\Seller;
use App\Models\ShippingProfile;
use App\Models\Store;
use App\Services\ShippingProfileService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ShippingProfile CRUD — Shopify-style profile management.
 *
 * Phase 1 (this file): seller top-level CRUD with a store filter, mirrors the
 * StoreLocationController allXxx pattern. The General profile (`is_default`)
 * cannot be deleted — observer enforces this and the index hides the button.
 *
 * Phase 2 will add nested zone CRUD under the profile edit page; phase 3 wires
 * the admin nested per-store routes (controller is PanelAware-ready).
 */
class ShippingProfileController extends Controller
{
    use PanelAware, AuthorizesRequests, ChecksPermissions;

    protected bool $viewPermission = true;
    protected bool $createPermission = true;
    protected bool $editPermission = true;
    protected bool $deletePermission = true;

    public function __construct(protected ShippingProfileService $service)
    {
        $panel = $this->getPanel();
        $enum = $panel === 'admin' ? AdminPermissionEnum::class : SellerPermissionEnum::class;
        $user = auth()->user();

        $this->viewPermission   = $this->hasPermission($enum::SHIPPING_PROFILE_VIEW()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        $this->createPermission = $this->hasPermission($enum::SHIPPING_PROFILE_CREATE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        $this->editPermission   = $this->hasPermission($enum::SHIPPING_PROFILE_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        $this->deletePermission = $this->hasPermission($enum::SHIPPING_PROFILE_DELETE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
    }


    /**
     * GET /seller/shipping?store_id=X — cross-store list with store filter.
     */
    public function allIndex(Request $request): View
    {
        abort_unless($this->viewPermission, 403);
        $seller = $this->requireSeller();
        $stores = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->orderBy('name')->get();
        $selectedStoreId = app(\App\Services\SellerStoreService::class)->selectedId();

        $profiles = ShippingProfile::query()
            ->with(['store:id,name,slug', 'locations:id', 'products:id', 'zones:id,shipping_profile_id'])
            ->whereIn('store_id', $stores->pluck('id'))
            ->when($selectedStoreId, fn ($q) => $q->where('store_id', $selectedStoreId))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $productCounts = $this->buildEffectiveProductCounts($profiles, $stores);

        return view('seller.shipping.index', [
            'stores'           => $stores,
            'selectedStoreId'  => $selectedStoreId,
            'profiles'         => $profiles,
            'productCounts'    => $productCounts,
            'createPermission' => $this->createPermission,
            'editPermission'   => $this->editPermission,
            'deletePermission' => $this->deletePermission,
        ]);
    }

    /**
     * Build a map of profile_id → effective product count.
     *
     * Products are seller-owned (not store-scoped), so the seller's total
     * product count is shared across every store's General profile. We
     * compute it once and subtract the in-memory pivot counts.
     */
    protected function buildEffectiveProductCounts($profiles, $stores): array
    {
        $counts = [];

        $sellerId = $stores->first()?->seller_id;
        $sellerTotal = $sellerId
            ? \App\Models\Product::where('seller_id', $sellerId)->count()
            : 0;

        $totalCustomAssigned = 0;
        foreach ($profiles as $p) {
            if (!$p->is_default) {
                $totalCustomAssigned += $p->products->count();
            }
        }

        $defaultFallback = max(0, $sellerTotal - $totalCustomAssigned);

        foreach ($profiles as $p) {
            $counts[$p->id] = $p->is_default ? $defaultFallback : $p->products->count();
        }

        return $counts;
    }

    /**
     * GET /seller/shipping/create — form with a Store picker at the top.
     */
    public function allCreate(Request $request): View
    {
        abort_unless($this->viewPermission, 403);
        $seller = $this->requireSeller();
        $stores = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->orderBy('name')->get();
        $preselectedStoreId = app(\App\Services\SellerStoreService::class)->selectedId() ?: $stores->first()?->id;

        $store = $preselectedStoreId
            ? $stores->firstWhere('id', $preselectedStoreId)
            : null;

        return view('seller.shipping.form', [
            'stores'    => $stores,
            'storeId'   => $preselectedStoreId,
            'profile'   => new ShippingProfile(),
            'locations' => $store ? $store->locations()->orderByDesc('is_default')->orderBy('name')->get() : collect(),
            'products'  => $store ? $this->productsForStore($store) : collect(),
            'selectedLocationIds' => [],
            'rankedLocations'     => collect(),
            'selectedProductIds'  => [],
            'zones'     => collect(),
            'action'    => 'create',
        ]);
    }

    /**
     * POST /seller/shipping
     */
    public function allStore(StoreShippingProfileRequest $request): JsonResponse
    {
        try {
            if (!$this->createPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $seller = $this->requireSeller();
            $storeId = (int) $request->input('store_id');
            $store = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->findOrFail($storeId);

            $profile = $this->service->create($store, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.shipping_profile_created'),
                data: ['profile' => $profile, 'redirect' => route('seller.shipping.index')],
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
     * GET /seller/shipping/{profile}/edit — store locked.
     */
    public function allEdit($profileId): View
    {
        abort_unless($this->viewPermission, 403);
        $seller = $this->requireSeller();
        $profile = ShippingProfile::whereIn('store_id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())
            ->with([
                'locations:id',
                'products:id',
                'zones' => fn ($q) => $q->orderBy('priority')->orderBy('name'),
                'zones.countries:id,iso2,name',
                'zones.serviceabilityRules',
                'zones.rates',
            ])
            ->findOrFail($profileId);
        $store = $profile->store;
        $stores = $seller->stores()->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())->orderBy('name')->get();

        return view('seller.shipping.form', [
            'stores'    => $stores,
            'storeId'   => $profile->store_id,
            'profile'   => $profile,
            'locations' => $store->locations()->orderByDesc('is_default')->orderBy('name')->get(),
            'products'  => $this->productsForStore($store),
            'selectedLocationIds' => $profile->locations->pluck('id')->all(),
            'rankedLocations'     => $profile->locations()
                ->orderByPivot('priority')
                ->orderBy('store_locations.name')
                ->get(['store_locations.id', 'store_locations.name', 'store_locations.is_default']),
            'selectedProductIds'  => $profile->products->pluck('id')->all(),
            'zones'     => $profile->zones,
            'zoneCountries' => $store->allowedShippingCountries()
                ->map(fn ($c) => ['iso2' => $c->iso2, 'name' => $c->name])
                ->values(),
            'action'    => 'edit',
        ]);
    }

    /**
     * POST /seller/shipping/{profile}
     */
    public function allUpdate(UpdateShippingProfileRequest $request, $profileId): JsonResponse
    {
        try {
            if (!$this->editPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $seller = $this->requireSeller();
            $profile = ShippingProfile::whereIn('store_id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())
                ->findOrFail($profileId);

            $profile = $this->service->update($profile, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.shipping_profile_updated'),
                data: ['profile' => $profile, 'redirect' => route('seller.shipping.index')],
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        }
    }

    /**
     * DELETE /seller/shipping/{profile}
     *
     * The ShippingProfileObserver throws DomainException on the default
     * profile — we surface that as a user-facing message rather than a 500.
     */
    public function allDestroy($profileId): JsonResponse
    {
        try {
            if (!$this->deletePermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $seller = $this->requireSeller();
            $profile = ShippingProfile::whereIn('store_id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds())
                ->findOrFail($profileId);
            $this->service->delete($profile);

            return ApiResponseType::sendJsonResponse(true, __('labels.shipping_profile_deleted'), []);
        } catch (\DomainException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.cannot_delete_default_shipping_profile'), []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_found'), []);
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
     * Returns an empty collection — the picker loads via the
     * productsSearch endpoint instead of pre-dumping every product into
     * the page. The view variable is kept for backward compat with the
     * blade slot; only `selectedProductIds` is meaningful now.
     */
    protected function productsForStore(Store $store)
    {
        return collect();
    }

    /**
     * GET /seller/shipping/products-search?search=Q&find_in[]=ID
     *
     * Backs the TomSelect remote loader on the profile form's product
     * picker. Products are seller-owned (no store_id on products) — same
     * scoping as ProductController::buildBaseQuery / the seller's main
     * products screen. Two modes:
     *   - `search`  → fuzzy title match, capped at 50 results
     *   - `find_in` → exact ID lookup for pre-loading current selection
     *                 on edit so chips render with the right titles
     * `store_id` is accepted but ignored — kept in the signature so the
     * picker can stay future-proof if we ever scope by store again.
     */
    public function productsSearch(Request $request): \Illuminate\Http\JsonResponse
    {
        $seller = $this->requireSeller();

        $query = Product::query()->where('seller_id', $seller->id);

        if ($findIn = $request->query('find_in')) {
            $query->whereIn('id', (array) $findIn);
        } elseif ($search = (string) $request->query('search', '')) {
            $term = trim($search);
            if ($term !== '') {
                $query->where('title', 'like', '%' . $term . '%');
            }
        }

        $rows = $query->orderBy('title')
            ->limit(50)
            ->get(['id', 'title']);

        return response()->json($rows->map(fn ($p) => [
            'value' => (string) $p->id,
            'text'  => $p->title,
        ])->all());
    }
}
