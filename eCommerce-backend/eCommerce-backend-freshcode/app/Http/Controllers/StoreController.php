<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\BankAccountTypeEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Events\Store\StoreConfigUpdated;
use App\Events\Store\StoreCreated;
use App\Events\Store\StoreUpdated;
use App\Events\Store\StoreVerificationUpdate;
use App\Http\Requests\Seller\StoreStoreConfigurationRequest;
use App\Http\Requests\Store\StoreStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Http\Requests\Store\VerifyStoreRequest;
use App\Models\Country;
use App\Models\Market;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Services\StoreService;
use App\Services\SubscriptionUsageService;
use App\Traits\SubscriptionLimitGuard;
use App\Services\SpatieMediaService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use App\Enums\Subscription\SubscriptionPlanKeyEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StoreController extends Controller
{
    use PanelAware, AuthorizesRequests, ChecksPermissions, SubscriptionLimitGuard;

    protected bool $editPermission = true;
    protected bool $deletePermission = true;
    protected bool $createPermission = true;
    protected bool $viewPermission = true;
    protected bool $verifyPermission = true;
    public int $sellerId;

    /**
     * @throws AuthorizationException
     */

    public function __construct()
    {
        $user = auth()->user();
        $seller = $user?->seller();
        $this->sellerId = $seller ? $seller->id : 0;


        if ($this->getPanel() === 'seller') {
            $this->editPermission = $this->hasPermission(SellerPermissionEnum::STORE_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->deletePermission = $this->hasPermission(SellerPermissionEnum::STORE_DELETE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->createPermission = $this->hasPermission(SellerPermissionEnum::STORE_CREATE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->viewPermission = $this->hasPermission(SellerPermissionEnum::STORE_VIEW()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        }
        if ($this->getPanel() === 'admin') {
            $this->verifyPermission = $this->hasPermission(AdminPermissionEnum::STORE_VERIFY());
        }
    }

    /**
     * Switch the store the whole seller panel is scoped to (global header
     * selector). Bounded to the seller's own stores by SellerStoreService, so
     * an id that isn't theirs is silently ignored.
     */
    public function switchSellerStore(Request $request, \App\Services\SellerStoreService $stores): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['store_id' => 'required|integer']);

        $store = $stores->setStore((int) $request->input('store_id'));

        if (!$store) {
            return back()->withErrors(['store_id' => __('labels.store_not_found')]);
        }

        return back();
    }

    public function index($id = null): View
    {
        $this->authorize('viewAny', Store::class);
        $seller = null;
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'name', 'name' => 'name', 'title' => __('labels.name')],
            ['data' => 'city', 'name' => 'city', 'title' => __('labels.city')],
            ['data' => 'contact_number', 'name' => 'contact_number', 'title' => __('labels.contact_number')],
            ['data' => 'verification_status', 'name' => 'verification_status', 'title' => __('labels.verification_status')],
            ['data' => 'visibility_status', 'name' => 'visibility_status', 'title' => __('labels.visibility_status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
        ];
        if ($this->getPanel() === 'seller') {
            $columns[] = ['data' => 'store_configuration', 'name' => 'store_configuration', 'title' => __('labels.store_configuration')];
            $columns[] = ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false];
        } else {
            $columns[] = ['data' => 'recommended', 'name' => 'recommended', 'title' => __('labels.recommended')];
            $columns[] = ['data' => 'seller_name', 'name' => 'seller_name', 'title' => __('labels.seller_name')];
            $columns[] = ['data' => 'actions', 'name' => 'actions', 'title' => __('labels.actions')];
            if ($id != null) {
                $seller = Seller::findOrFail($id);
            }
        }
        $verificationStatus = StoreVerificationStatusEnum::values();
        $visibilityStatus = StoreVisibilityStatusEnum::values();

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;
        $storeCreateLimitReached = false;
        $storeCreateLimitMessage = null;

        if (
            $this->getPanel() === 'seller'
            && $createPermission
            && Setting::isSubscriptionEnabled()
        ) {
            $limitKey = SubscriptionPlanKeyEnum::STORE_LIMIT();
            $usageService = app(SubscriptionUsageService::class);
            $limit = $usageService->getLimit((int)$this->sellerId, $limitKey);
            $used = $usageService->getUsage((int)$this->sellerId, $limitKey);

            if ($limit >= 0 && $used >= $limit) {
                $storeCreateLimitReached = true;
                $storeCreateLimitMessage = __('labels.subscription_limit_exceeded', [
                    'key' => Str::ucfirst(Str::replace('_', ' ', $limitKey)),
                    'limit' => $limit,
                    'used' => $used,
                    'remaining' => max(0, $limit - $used),
                ]);
            }
        }

        return view($this->panelView('stores.index'), compact(
            'columns',
            'editPermission',
            'deletePermission',
            'createPermission',
            'seller',
            'verificationStatus',
            'visibilityStatus',
            'storeCreateLimitReached',
            'storeCreateLimitMessage'
        ));
    }

    /**
     * @throws AuthorizationException
     */
    public function getStores(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Store::class);
        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';
        $sellerId = $request->get('seller_id');
        $visibilityStatus = $request->get('visibility_status');
        $verificationStatus = $request->get('verification_status');
        $recommendedStatus = $request->get('recommended_status');

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';

        $columns = ['id', 'name', 'city', 'contact_number', 'verification_status', 'visibility_status', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';
        $query = Store::query()->with('seller.user');
        if ($visibilityStatus !== null) {
            $query->where('visibility_status', $visibilityStatus);
        }
        if ($verificationStatus !== null) {
            $query->where('verification_status', $verificationStatus);
        }
        if ($recommendedStatus !== null && $recommendedStatus !== '') {
            $query->where('is_recommended', filter_var($recommendedStatus, FILTER_VALIDATE_BOOLEAN));
        }
        if ($this->getPanel() === 'seller') {
            $user = auth()->user();
            $seller = $user->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'Seller not found');
            }
            $query->where('seller_id', $seller->id)
                ->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds());
        } elseif ($sellerId) {
            if (strpos($sellerId, ',') !== false) {
                $sellerIds = explode(',', $sellerId);
                $query->whereIn('seller_id', $sellerIds);
            } else {
                $query->where('seller_id', $sellerId);
            }
        }

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%$searchValue%")
                    ->orWhere('city', 'like', "%$searchValue%")
                    ->orWhere('contact_number', 'like', "%$searchValue%")
                    ->orWhereHas('seller.user', function ($q) use ($searchValue) {
                        $q->where('name', 'like', "%$searchValue%");
                    });
            });
        }

        $totalRecords = $filteredRecords = $query->count();

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($store) use ($editPermission, $deletePermission) {
                $data = [
                    'id' => $store->id,
                    'name' => e($store->name),
                    'city' => e($store->city),
                    'contact_number' => e($store->contact_number),
                    'verification_status' => view('partials.status', [
                        'status' => $store->verification_status->value,
                    ])->render(),
                    'visibility_status' => view('partials.status', [
                        'status' => $store->visibility_status->value,
                    ])->render(),
                    'created_at' => $store->created_at->format('Y-m-d'),
                ];
                if ($this->getPanel() === 'seller') {
                    $data += [
                        'store_configuration' => view('seller.stores.partials.store-config-button', ['store' => $store])->render(),
                        'action' => view('partials.actions', [
                            'modelName' => 'store',
                            'id' => $store->id,
                            'title' => $store->name,
                            'mode' => 'page_view',
                            'route' => route($this->panelView('stores.edit'), $store->id),
                            'editPermission' => $editPermission,
                            'deletePermission' => $deletePermission
                        ])->render(),
                    ];
                } else {
                    $data += [
                        'recommended' => $store->is_recommended
                            ? '<span class="badge bg-info-lt">'.e(__('labels.recommended')).'</span>'
                            : '<span class="text-secondary">-</span>',
                        'seller_name' => $store->seller->user->name ?? '',
                        'actions' => view('admin.stores.partials.actions', ['store' => $store])->render(),
                    ];
                }
                return $data;
            })
            ->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function create(): View
    {
        $this->authorize('create', Store::class);

        $selectedMarketIds = [];

        return view($this->panelView('stores.form'), compact('selectedMarketIds'));
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Store::class);
            DB::beginTransaction();
            $request->validated();
            $user = auth()->user();
            $seller = $user->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'Seller not found');
            }
            $validated = $request->safe()->all();
            $validated['seller_id'] = $seller->id;


            $validated['verification_status'] = StoreVerificationStatusEnum::NOT_APPROVED();
            $validated['visibility_status']   = StoreVisibilityStatusEnum::DRAFT();

            if ($error = $this->ensureCanUseOrError($seller->id, SubscriptionPlanKeyEnum::STORE_LIMIT())) {
                DB::rollBack();
                return $error;
            }

            $store = Store::create($validated);

            $this->applyDefaultLocationAddress($store, $request->input('location', []));

            $this->recordUsageIfMultivendor($seller->id, SubscriptionPlanKeyEnum::STORE_LIMIT());

            $this->syncStoreReach($store, $request);
            if ($request->hasFile('store_logo')) {
                SpatieMediaService::upload($store, SpatieMediaCollectionName::STORE_LOGO());
            }
            if ($request->hasFile('store_banner')) {
                SpatieMediaService::upload($store, SpatieMediaCollectionName::STORE_BANNER());
            }
            event(new StoreCreated($store));
            DB::commit();
            return ApiResponseType::sendJsonResponse(success: true, message: 'Store created successfully', data: $store);
        } catch (AuthorizationException $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(success: false, message: 'Unauthorized action: ' . $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(success: false, message: 'Error creating store: ' . $e->getMessage());
        }
    }

    private function applyDefaultLocationAddress(Store $store, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $location = $store->locations()->where('is_default', true)->first();
        if (!$location) {
            return;
        }

        $countryName = $data['country_name'] ?? null;
        if (blank($countryName) && !empty($data['country_iso2'])) {
            $countryName = \App\Models\Country::where('iso2', $data['country_iso2'])->value('name');
        }

        $location->fill([
            'name'         => filled($data['name'] ?? null) ? $data['name'] : $location->name,
            'address_1'    => $data['address_1'] ?? null,
            'address_2'    => $data['address_2'] ?? null,
            'city'         => $data['city'] ?? null,
            'state'        => $data['state'] ?? null,
            'country_iso2' => $data['country_iso2'] ?? null,
            'country_name' => $countryName,
            'pin_code'     => $data['pin_code'] ?? null,
            'latitude'     => $data['latitude'] ?? null,
            'longitude'    => $data['longitude'] ?? null,
        ])->save();
    }

    /**
     * @throws AuthorizationException
     */
    public function edit($id): View
    {
        $store = Store::with(['markets:id'])->findOrFail($id);
        $this->authorize('update', $store);
        $this->assertSellerStoreAccessible($store);

        $selectedMarketIds = $store->markets->pluck('id')->all();

        return view($this->panelView('stores.form'), compact('store', 'selectedMarketIds'));
    }

    public function show($id): View
    {
        $store = Store::with(['markets:id,code,name'])->findOrFail($id);
        $this->authorize('view', $store);
        $this->assertSellerStoreAccessible($store);
        $verificationStatus = StoreVerificationStatusEnum::values();
        $visibilityStatus = StoreVisibilityStatusEnum::values();
        $verifyPermission = $this->verifyPermission;
        return view($this->panelView('stores.view'), compact('store', 'verificationStatus', 'visibilityStatus', 'verifyPermission'));
    }

    public function update(UpdateStoreRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $store = Store::findOrFail($id);
            $validated = $request->validated();


            $this->authorize('update', $store);
            $this->assertSellerStoreAccessible($store);

            $store->update($validated);
            if ($request->hasFile('store_logo')) {
                SpatieMediaService::update($request, $store, SpatieMediaCollectionName::STORE_LOGO());
            }
            if ($request->hasFile('store_banner')) {
                SpatieMediaService::update($request, $store, SpatieMediaCollectionName::STORE_BANNER());
            }

            $this->syncStoreReach($store, $request);

            event(new StoreUpdated($store));
            DB::commit();
            return ApiResponseType::sendJsonResponse(true, 'Store updated successfully', ['store' => $store]);
        } catch (AuthorizationException $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, 'Unauthorized action: ' . $e->getMessage(), []);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, 'Store not found', []);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, 'Error updating store: ' . $e->getMessage(), []);
        }
    }

    public function destroy($id, StoreService $storeService): JsonResponse
    {
        try {
            $store = Store::findOrFail($id);
            $this->authorize('delete', $store);
            $this->assertSellerStoreAccessible($store);

            $blockers = $storeService->getDeleteBlockers($store);
            if (!empty($blockers)) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    __('labels.store_delete_blocked', ['items' => implode(', ', $blockers)]),
                    []
                );
            }

            DB::beginTransaction();
            $sellerId = $store->seller_id;
            $storeService->deleteForSeller($store);

            if ($sellerId) {
                $this->reduceUsageIfMultivendor((int)$sellerId, SubscriptionPlanKeyEnum::STORE_LIMIT());
            }
            DB::commit();
            return ApiResponseType::sendJsonResponse(true, __('labels.store_deleted_successfully') ?? 'Store deleted successfully', []);

        } catch (AuthorizationException $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, 'Unauthorized action: ' . $e->getMessage(), []);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, 'Store not found', []);
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), []);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, 'Error deleting store: ' . $e->getMessage(), []);
        }
    }

    public function configuration($id): View
    {
        $store = Store::find($id);
        if (!$store) {
            abort(404, 'Store not found');
        }
        $this->authorize('view', $store);
        $this->assertSellerStoreAccessible($store);
        return view($this->panelView('stores.configuration'), compact('store'));
    }

    public function storeConfiguration(StoreStoreConfigurationRequest $request, $id): JsonResponse
    {
        try {
            $store = Store::findOrFail($id);
            $this->authorize('update', $store);
            $this->assertSellerStoreAccessible($store);
            $validated = $request->validated();
            $store->update($validated);
            event(new StoreConfigUpdated($store));
            return ApiResponseType::sendJsonResponse(true, 'Store Configuration updated successfully', ['store' => $store]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Unauthorized action: ' . $e->getMessage(), []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Store not found', []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, 'Error updating store configuration: ' . $e->getMessage(), []);
        }
    }


    public function verify(VerifyStoreRequest $request, $id): JsonResponse
    {
        try {
            $this->authorize('verifyStore', Store::class);
            $store = Store::findOrFail($id);
            $validated = $request->validated();
            if (
                !empty($validated['is_recommended']) &&
                ($validated['verification_status'] ?? null) !== StoreVerificationStatusEnum::APPROVED()
            ) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'Please approve the store before marking it as recommended',
                    []
                );
            }
            $store->update($validated);
            event(new StoreVerificationUpdate($store));
            return ApiResponseType::sendJsonResponse(true, 'Store verified successfully', ['store' => $store]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Unauthorized action: ' . $e->getMessage(), []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(false, 'Store not found', []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, 'Error updating store configuration: ' . $e->getMessage(), []);
        }
    }

    public function toggleRecommended($id): JsonResponse
{
    try {
        $this->authorize('verifyStore', Store::class);

        $store = Store::findOrFail($id);

        if (($store->verification_status?->value ?? $store->verification_status) !== StoreVerificationStatusEnum::APPROVED()) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Please approve the store before marking it as recommended',
                []
            );
        }

        $store->is_recommended = ! $store->is_recommended;
        $store->save();

        return ApiResponseType::sendJsonResponse(
            true,
            $store->is_recommended
                ? 'Store marked as recommended successfully'
                : 'Store removed from recommended successfully',
            ['store' => $store]
        );
    } catch (AuthorizationException $e) {
        return ApiResponseType::sendJsonResponse(false, 'Unauthorized action: '.$e->getMessage(), []);
    } catch (ModelNotFoundException $e) {
        return ApiResponseType::sendJsonResponse(false, 'Store not found', []);
    } catch (\Exception $e) {
        return ApiResponseType::sendJsonResponse(false, 'Error updating store recommendation: '.$e->getMessage(), []);
    }
}


    public function StoreList(Request $request): JsonResponse
    {
        $sellerId = null;

        if ($this->getPanel() === 'admin') {
            $sellerId = $request->query('seller_id');
            if (!is_numeric($sellerId)) {
                return ApiResponseType::sendJsonResponse(success: true, message: 'Stores retrieved successfully', data: []);
            }
            $sellerId = (int) $sellerId;
        } else {
            $seller = auth()->user()?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'Seller ID is required');
            }
            $sellerId = (int) $seller->id;
        }

        $storesQuery = Store::query()->where('seller_id', $sellerId);
        if ($this->getPanel() === 'seller') {
            $storesQuery->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds());
        }

        $stores = $storesQuery
            ->get()
            ->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'city' => $store->city,
                    'contact_number' => $store->contact_number,
                    'verification_status' => $store->verification_status,
                    'visibility_status' => $store->visibility_status
                ];
            });

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Stores retrieved successfully',
            data: $stores
        );
    }

    /**
     * Stores belonging to the seller, with their locations eager-loaded and
     * grouped underneath. Used by the seller product form's Inventory &
     * Locations grid (and by the seller mobile API) — both clients render the
     * same Sell-from / Track / Qty rows per (variant, location).
     *
     * Response shape:
     *   [{ id, name, locations: [{ id, name, is_default, is_active }, ...] }, ...]
     */
    public function StoresWithLocations(Request $request): JsonResponse
    {
        $sellerId = $this->getPanel() === 'admin'
            ? (int) $request->query('seller_id')
            : (int) (auth()->user()?->seller()?->id ?? 0);

        if ($sellerId <= 0) {
            return ApiResponseType::sendJsonResponse(true, 'Stores retrieved successfully', []);
        }

        $storesQuery = Store::query()->where('seller_id', $sellerId);
        if ($this->getPanel() === 'seller') {
            $storesQuery->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds());
        }

        $stores = $storesQuery
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

        return ApiResponseType::sendJsonResponse(true, 'Stores retrieved successfully', $stores);
    }


    public function search(Request $request): JsonResponse
    {
        $q = $request->input('search') ?? $request->input('q');
        $findId = $request->input('find_id');

        $query = Store::query()->select(['id', 'name']);

        if ($this->getPanel() === 'seller') {
            $seller = auth()->user()?->seller();
            if ($seller) {
                $query->where('seller_id', $seller->id)
                    ->whereIn('id', app(\App\Services\SellerStoreService::class)->accessibleStoreIds());
            } else {
                return response()->json([]);
            }
        }

        if (!empty($findId)) {
            $query->where('id', $findId);
        } elseif (!empty($q)) {
            $query->where('name', 'like', "%{$q}%");
        } else {
            $query->limit(20);
        }

        $stores = $query->orderBy('name')->get();

        $results = $stores->map(function ($store) {
            return [
                'id' => $store->id,
                'value' => $store->id,
                'text' => $store->name,
            ];
        });

        return response()->json($results);
    }

    /**
     * Sync the brand-reach pivot from the request (Phase A).
     *
     *   market_ids[]  → store_market
     *
     * Ships-to-countries is intentionally NOT handled here. It gets auto-
     * derived from the union of the store's locations' shipping_zones
     * coverage countries (Block 1B work) — sellers configure country reach
     * in one place (shipping zones), not two.
     *
     * Sync (not attach) is intentional — sellers editing the store can both
     * add and remove markets by changing the picker selection.
     */
    /**
     * On the seller panel, block access to a store the current user (a scoped
     * staff member) is not assigned to. Owners and unscoped staff pass. No-op on
     * the admin panel.
     */
    protected function assertSellerStoreAccessible(Store $store): void
    {
        if ($this->getPanel() !== 'seller') {
            return;
        }

        $accessible = app(\App\Services\SellerStoreService::class)->accessibleStoreIds();
        if (!in_array((int) $store->id, $accessible, true)) {
            throw new ModelNotFoundException('Store not found');
        }
    }

    protected function syncStoreReach(Store $store, $request): void
    {
        if ($request->has('market_ids')) {
            $marketIds = collect((array) $request->input('market_ids', []))
                ->filter()
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
            $store->markets()->sync($marketIds);
        }
    }
}
