<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductTypeEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Subscription\SubscriptionPlanKeyEnum;
use App\Http\Requests\Product\StoreUpdateProductRequest;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use App\Models\Market;
use App\Models\Product;
use App\Models\ProductModerationLog;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\StoreAddonItem;
use App\Models\StoreLocationInventory;
use App\Models\StoreProductVariant;
use App\Models\StoreProductVariantAddon;
use App\Services\CategoryService;
use App\Services\CurrencyService;
use App\Services\GlobalAttributeService;
use App\Services\ProductApprovalService;
use App\Services\ProductService;
use App\Services\SellerStoreService;
use App\Services\SubscriptionUsageService;
use App\Services\Visibility\ProductVisibilityInspector;
use App\Support\PricePresenter;
use App\Traits\AppliesDatatableDateRange;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Traits\SubscriptionLimitGuard;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    use AppliesDatatableDateRange, AuthorizesRequests, ChecksPermissions, PanelAware;
    use SubscriptionLimitGuard;

    public float $sellerId;

    protected bool $editPermission = false;

    protected bool $deletePermission = false;

    protected bool $createPermission = false;

    protected bool $viewPermission = false;

    protected bool $updateStatusPermission = false;

    public function __construct()
    {
        $user = auth()->user();
        $seller = $user?->seller();
        $this->sellerId = $seller ? $seller->id : 0;

        if ($this->getPanel() === 'seller') {
            $this->viewPermission = $this->hasPermission(SellerPermissionEnum::PRODUCT_VIEW()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->editPermission = $this->hasPermission(SellerPermissionEnum::PRODUCT_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->deletePermission = $this->hasPermission(SellerPermissionEnum::PRODUCT_DELETE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->createPermission = $this->hasPermission(SellerPermissionEnum::PRODUCT_CREATE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        } elseif ($this->getPanel() === 'admin') {
            $this->viewPermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_VIEW());
            $this->createPermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_CREATE());
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_DELETE());
            $this->updateStatusPermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_STATUS_UPDATE());
        }
    }

    /**
     * Resolve the seller this request is acting on:
     *   - seller panel: authenticated seller
     *   - admin panel:  seller_id submitted in the form (or already set on the product when editing)
     */
    protected function resolveSellerIdForWrite(?Product $product, $request): int
    {
        if ($this->getPanel() === 'admin') {
            $submitted = (int) ($request->input('seller_id') ?? 0);
            if ($submitted > 0) {
                return $submitted;
            }

            return (int) ($product?->seller_id ?? 0);
        }

        return (int) (auth()->user()?->seller()?->id ?? 0);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Product::class);

        $columns = [
            ['data' => 'select', 'name' => 'select', 'title' => '<input type="checkbox" id="select-all-products" class="form-check-input">', 'orderable' => false, 'searchable' => false],
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'product_details', 'name' => 'product_details', 'title' => __('labels.product_details')],
            ['data' => 'admin_approval_status', 'name' => 'admin_approval_status', 'title' => __('labels.admin_approval_status')],
            ['data' => 'updated_at', 'name' => 'updated_at', 'title' => __('labels.updated_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;
        $viewPermission = $this->viewPermission;
        $productCreateLimitReached = false;
        $productCreateLimitMessage = null;

        if (
            $this->getPanel() === 'seller'
            && $createPermission
            && Setting::isSubscriptionEnabled()
        ) {
            $limitKey = SubscriptionPlanKeyEnum::PRODUCT_LIMIT();
            $usageService = app(SubscriptionUsageService::class);
            $limit = $usageService->getLimit((int) $this->sellerId, $limitKey);
            $used = $usageService->getUsage((int) $this->sellerId, $limitKey);

            if ($limit >= 0 && $used >= $limit) {
                $productCreateLimitReached = true;
                $productCreateLimitMessage = __('labels.subscription_limit_exceeded', [
                    'key' => Str::ucfirst(Str::replace('_', ' ', $limitKey)),
                    'limit' => $limit,
                    'used' => $used,
                    'remaining' => max(0, $limit - $used),
                ]);
            }
        }

        return view($this->panelView('products.index'), compact(
            'columns',
            'editPermission',
            'deletePermission',
            'createPermission',
            'viewPermission',
            'productCreateLimitReached',
            'productCreateLimitMessage'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', Product::class);

        $categories = CategoryService::getCategoriesWithParent();
        $sellerIdForLookups = $this->getPanel() === 'admin'
            ? (int) (request()->query('seller_id') ?? 0)
            : (int) $this->sellerId;

        $sellerMarkets = $this->resolveSellerMarketsWithTaxClasses($sellerIdForLookups);
        $approvalCriticalLabels = app(ProductApprovalService::class)->criticalFieldLabels();
        $selectedSellerId = $sellerIdForLookups ?: null;
        $selectedSellerName = $selectedSellerId
            ? (string) (Seller::query()->with('user')->find($selectedSellerId)?->user?->name ?? '')
            : '';

        $categories = json_encode($categories->toArray());

        return view($this->panelView('products.form'), compact('categories', 'sellerMarkets', 'approvalCriticalLabels', 'selectedSellerId', 'selectedSellerName'));
    }

    /**
     * Markets the given seller's stores are enabled in, each with its tax
     * classes preloaded. Used by the form's Tax multi-select to filter
     * options to markets the seller actually sells in. Returns an empty
     * collection if the seller has no stores yet (form hides the Tax card).
     */
    protected function resolveSellerMarketsWithTaxClasses(?int $sellerId): Collection
    {
        if (! $sellerId) {
            return collect();
        }

        return Market::query()
            ->whereHas('stores', fn ($q) => $q->where('seller_id', $sellerId))
            ->with(['taxClasses' => fn ($q) => $q->with('taxRates:id,rate')->orderBy('title')])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'currency_code',
                'symbol_position',
                'space_between_symbol',
                'thousand_separator',
                'decimal_separator',
                'grouping_style',
                'decimal_places',
                'negative_format',
            ])
            ->each(fn (Market $market) => $market->setAttribute(
                'price_preview',
                PricePresenter::forMarket(1, $market),
            ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUpdateProductRequest $request, ProductService $productService): JsonResponse
    {
        try {
            $this->authorize('create', Product::class);

            $validated = $request->validated();
            $validated['seller_id'] = $this->resolveSellerIdForWrite(null, $request);
            if ($validated['seller_id'] <= 0) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'labels.seller_required', data: []);
            }
            if ($error = $this->ensureCanUseOrError($validated['seller_id'], SubscriptionPlanKeyEnum::PRODUCT_LIMIT())) {
                return $error;
            }

            $result = $productService->storeProduct($validated, $request);

            $this->recordUsageIfMultivendor($validated['seller_id'], SubscriptionPlanKeyEnum::PRODUCT_LIMIT());

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.product_created_successfully', data: [
                'product_id' => $result['product']->id,
                'product_uuid' => $result['product']->uuid,
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: collect($e->errors())->flatten()->first() ?? __('labels.validation_failed'),
                data: $e->errors(),
                status: 422,
            );
        } catch (\Exception $e) {
            Log::error('Error while creating product =>'.$e->getMessage());

            return ApiResponseType::sendJsonResponse(success: false, message: $e->getMessage(), data: $e->getMessage());
        }
    }

    /**
     * Return the visibility inspection for a product — what gates it passes
     * and which zones it's reachable in.
     */
    public function visibility($id, ProductVisibilityInspector $inspector): JsonResponse
    {
        try {
            $product = Product::find($id);
            if (! $product) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.product_not_found',
                    data: [],
                );
            }
            $this->authorize('view', $product);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.fetched_successfully',
                data: $inspector->inspect($product),
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('ProductController@visibility error: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.something_went_wrong',
                data: [],
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = ProductService::getProductWithVariants($id);
        if (! $product) {
            abort(404, 'Product Not Found');
        }
        $this->authorize('view', $product);

        $product->load([
            'comboPack.items.product',
            'comboPack.items.variant',
            'faqs',
            'category',
            'brand',
            'productCondition',
            'seller.user',
            'taxClasses.taxRates',
            'customProductSections.fields',
            'variants.attributes.attribute',
            'variants.attributes.attributeValue',
            'variants.locationInventories.storeLocation.store',
            'variants.storeProductVariants.store',
            'variants.media',
        ]);

        $product->product_video = $product->getFirstMediaUrl(SpatieMediaCollectionName::PRODUCT_VIDEO());
        $variants = $product->variants;
        $fulfillmentInventory = $variants
            ->flatMap(fn ($variant) => $variant->locationInventories)
            ->where('is_fulfillment_location', true);
        $hasUnlimitedStock = $fulfillmentInventory->contains(fn ($inventory) => ! $inventory->tracked);
        $availableStock = $hasUnlimitedStock
            ? null
            : $fulfillmentInventory->sum(fn ($inventory) => max(0, (int) $inventory->stock - (int) $inventory->reserved));
        $prices = $variants->map(fn ($variant) => $variant->price_exclude_tax)->filter(fn ($price) => $price !== null);
        $productViewStats = [
            'variant_count' => $variants->count(),
            'minimum_price' => $prices->isNotEmpty() ? PricePresenter::forCurrent((float) $prices->min())['formatted'] : null,
            'maximum_price' => $prices->isNotEmpty() ? PricePresenter::forCurrent((float) $prices->max())['formatted'] : null,
            'has_unlimited_stock' => $hasUnlimitedStock,
            'available_stock' => $availableStock,
            'location_count' => $fulfillmentInventory->pluck('store_location_id')->unique()->count(),
            'store_count' => $fulfillmentInventory
                ->map(fn ($inventory) => $inventory->storeLocation?->store_id)
                ->filter()
                ->unique()
                ->count(),
        ];
        $updateStatusPermission = $this->updateStatusPermission;
        $editPermission = $this->editPermission;

        $variantAddons = $this->buildProductVariantAddonMatrix($variants);

        return view($this->panelView('products.show'), compact(
            'product',
            'updateStatusPermission',
            'editPermission',
            'variantAddons',
            'productViewStats',
        ));
    }

    /**
     * Build a nested view-model describing every addon attachment on a
     * product's variants, grouped by variant → store → addon group, with
     * per-store price + stock + availability folded in for each addon item.
     *
     * Shape returned (keyed by variant_id):
     *   [
     *     42 => [
     *       'variant_id'    => 42,
     *       'variant_title' => '14 inch',
     *       'stores' => [
     *         [
     *           'store_id'   => 7,
     *           'store_name' => 'Downtown',
     *           'groups' => [
     *             [
     *               'group_id'       => 3,
     *               'group_title'    => 'Toppings',
     *               'selection_type' => 'multiple',
     *               'is_required'    => false,
     *               'items' => [
     *                 [
     *                   'item_id'      => 10,
     *                   'item_title'   => 'Extra cheese',
     *                   'indicator'    => 'veg',
     *                   'price'        => 1.50,  // from store_addon_items
     *                   'stock'        => 50,    // from store_addon_items, null if no row
     *                   'is_available' => true,
     *                 ],
     *               ],
     *             ],
     *           ],
     *         ],
     *       ],
     *     ],
     *   ]
     *
     * Returns an empty array when no attachments exist — the blade can
     * short-circuit and skip rendering the card entirely.
     *
     * @param  Collection<int,ProductVariant>  $variants
     * @return array<int, array<string, mixed>>
     */
    private function buildProductVariantAddonMatrix($variants): array
    {
        $variantIds = collect($variants)->pluck('id')->filter()->all();
        if (empty($variantIds)) {
            return [];
        }

        $attachments = StoreProductVariantAddon::with(['store', 'addonGroup', 'addonItem'])
            ->whereIn('product_variant_id', $variantIds)
            ->get();

        if ($attachments->isEmpty()) {
            return [];
        }

        $inventoryByPair = StoreAddonItem::query()
            ->whereIn('store_id', $attachments->pluck('store_id')->unique()->all())
            ->whereIn('addon_item_id', $attachments->pluck('addon_item_id')->unique()->all())
            ->get()
            ->keyBy(fn (StoreAddonItem $row) => ((int) $row->store_id).':'.((int) $row->addon_item_id));

        $variantTitles = collect($variants)->keyBy('id');

        $matrix = [];

        foreach ($attachments->groupBy('product_variant_id') as $variantId => $variantAttachments) {
            $stores = [];

            foreach ($variantAttachments->groupBy('store_id') as $storeId => $storeAttachments) {
                $firstStore = $storeAttachments->first();

                $groups = [];
                foreach ($storeAttachments->groupBy('addon_group_id') as $groupId => $groupAttachments) {
                    $firstGroup = $groupAttachments->first()->addonGroup ?? null;

                    $items = $groupAttachments->map(function ($row) use ($inventoryByPair) {
                        $inventory = $inventoryByPair->get(((int) $row->store_id).':'.((int) $row->addon_item_id));
                        $catalog = $row->addonItem;

                        return [
                            'item_id' => (int) $row->addon_item_id,
                            'item_title' => $catalog?->title ?? '—',
                            'indicator' => $catalog?->indicator?->value,
                            'price' => $inventory
                                ? (float) $inventory->price
                                : (float) ($catalog?->price ?? 0),
                            'stock' => $inventory ? (int) $inventory->stock : null,
                            'is_available' => $inventory
                                ? (bool) $inventory->is_available
                                : (bool) ($catalog?->is_available ?? true),
                        ];
                    })->values()->all();

                    $groups[] = [
                        'group_id' => (int) $groupId,
                        'group_title' => $firstGroup?->title ?? '—',
                        'selection_type' => $firstGroup?->selection_type?->value,
                        'is_required' => (bool) ($firstGroup?->is_required ?? false),
                        'items' => $items,
                    ];
                }

                $stores[] = [
                    'store_id' => (int) $storeId,
                    'store_name' => $firstStore->store?->name ?? '—',
                    'groups' => $groups,
                ];
            }

            $matrix[(int) $variantId] = [
                'variant_id' => (int) $variantId,
                'variant_title' => $variantTitles->get($variantId)?->title ?? '—',
                'stores' => $stores,
            ];
        }

        return $matrix;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $product = ProductService::getProductWithVariants($id);
        if (! $product) {
            abort(404, 'Product Not Found');
        }
        $product->load(['comboPack.store', 'comboPack.items.product', 'comboPack.items.variant.storeProductVariants']);
        $this->authorize('update', $product);
        $productVariants = null;
        $singleProductVariant = null;
        if ($product->type === ProductTypeEnum::VARIANT()) {
            foreach ($product->variants as $key => $variant) {
                $product->variants[$key]->image = $variant->image;
            }
            $productVariants = $product->variants;
        } else {
            $singleProductVariant = $product->variants->first();
        }
        $product->product_video = $product->getFirstMediaUrl(SpatieMediaCollectionName::PRODUCT_VIDEO());
        $categories = CategoryService::getCategoriesWithParent();

        $effectiveSellerId = ($this->getPanel() === 'admin') ? $product->seller_id : $this->sellerId;
        $sellerMarkets = $this->resolveSellerMarketsWithTaxClasses((int) $effectiveSellerId);
        $approvalCriticalLabels = app(ProductApprovalService::class)->criticalFieldLabels();
        $updateStatusPermission = $this->updateStatusPermission;
        $categories = json_encode($categories->toArray());

        return view($this->panelView('products.form'), compact('product', 'productVariants', 'singleProductVariant', 'categories', 'sellerMarkets', 'approvalCriticalLabels', 'updateStatusPermission'));
    }

    public function attributeOptions(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission || $this->createPermission || $this->editPermission, 403);

        $sellerId = $this->productLookupSellerId($request);
        if ($sellerId <= 0) {
            return response()->json(['data' => [], 'pagination' => ['more' => false]]);
        }

        $selectedIds = collect((array) $request->input('ids', []))->map(fn ($id) => (int) $id)->filter()->values();
        $search = trim((string) $request->input('search', $request->input('q', '')));
        $perPage = 20;

        $query = GlobalProductAttribute::query()
            ->where('seller_id', $sellerId)
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($selectedIds->isNotEmpty() && $search === '', fn (Builder $query) => $query->whereIn('id', $selectedIds))
            ->orderBy('title');

        $attributes = $query->paginate($perPage);

        return response()->json([
            'data' => collect($attributes->items())->map(fn (GlobalProductAttribute $attribute) => [
                'value' => (string) $attribute->id,
                'text' => $attribute->title,
                'swatche_type' => $attribute->swatche_type,
            ])->values(),
            'pagination' => ['more' => $attributes->hasMorePages()],
        ]);
    }

    public function kitComponentOptions(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission || $this->createPermission || $this->editPermission, 403);
        $sellerId = $this->productLookupSellerId($request);
        $store = \App\Models\Store::query()
            ->whereKey((int) $request->input('store_id'))
            ->where('seller_id', $sellerId)
            ->firstOrFail();
        if ($this->getPanel() === 'seller' && ! in_array((int) $store->id, app(SellerStoreService::class)->accessibleStoreIds(), true)) {
            abort(403);
        }

        $term = trim((string) $request->input('q', ''));
        if ($term === '') {
            return response()->json(['results' => []]);
        }

        $excludedVariantIds = collect((array) $request->input('exclude_variant_ids', []))
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($id) => (int) $id)
            ->values();

        $variants = ProductVariant::query()->with('product:id,title')
            ->whereHas('product', fn (Builder $query) => $query
                ->where('seller_id', $sellerId)
                ->where('status', ProductStatusEnum::ACTIVE())
                ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                ->whereDoesntHave('comboPack'))
            ->where(function (Builder $query) use ($term) {
                $query->where('title', 'like', '%'.$term.'%')
                    ->orWhere('sku', 'like', '%'.$term.'%')
                    ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->where('title', 'like', '%'.$term.'%'));
            })
            ->whereHas('storeProductVariants', fn (Builder $query) => $query->where('store_id', $store->id))
            ->when($excludedVariantIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $excludedVariantIds))
            ->orderBy('product_id')
            ->limit(20)
            ->get();

        $storeIdsByVariant = collect($variants)->mapWithKeys(fn (ProductVariant $variant) => [
            $variant->id => app(\App\Services\KitStoreAvailabilityService::class)->storesForVariant(
                (int) $variant->id,
                $sellerId,
                $this->getPanel() === 'seller' ? app(SellerStoreService::class)->accessibleStoreIds() : [],
            ),
        ]);

        return response()->json(['results' => $variants->map(fn (ProductVariant $variant) => [
            'id' => $variant->id,
            'text' => $variant->product->title.' — '.($variant->title ?: __('labels.default_title')),
            'price' => (float) $variant->getRawOriginal('price'),
            'store_ids' => $storeIdsByVariant->get($variant->id, []),
        ])]);
    }

    public function attributeValueOptions(Request $request, int $attribute): JsonResponse
    {
        abort_unless($this->viewPermission || $this->createPermission || $this->editPermission, 403);

        $sellerId = $this->productLookupSellerId($request);
        $parent = GlobalProductAttribute::query()
            ->whereKey($attribute)
            ->where('seller_id', $sellerId)
            ->firstOrFail();

        $selectedIds = collect((array) $request->input('ids', []))->map(fn ($id) => (int) $id)->filter()->values();
        $search = trim((string) $request->input('search', $request->input('q', '')));
        $perPage = max(30, min(100, $selectedIds->count()));
        $values = GlobalProductAttributeValue::query()
            ->with('attribute:id,swatche_type')
            ->where('global_attribute_id', $parent->id)
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($selectedIds->isNotEmpty() && $search === '', fn (Builder $query) => $query->whereIn('id', $selectedIds))
            ->orderBy('title')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($values->items())->map(fn (GlobalProductAttributeValue $value) => [
                'value' => (string) $value->id,
                'text' => $value->title,
                'swatche_type' => $parent->swatche_type,
                'swatche_value' => $value->swatche_value,
            ])->values(),
            'pagination' => ['more' => $values->hasMorePages()],
        ]);
    }

    private function productLookupSellerId(Request $request): int
    {
        if ($this->getPanel() === 'admin') {
            return (int) $request->input('seller_id', 0);
        }

        return (int) $this->sellerId;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreUpdateProductRequest $request, string $id, ProductService $productService): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

            $this->authorize('update', $product);

            $validated = $request->validated();

            $validated['seller_id'] = $this->resolveSellerIdForWrite($product, $request);
            if ($validated['seller_id'] <= 0) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'labels.seller_required', data: []);
            }

            $result = $productService->updateProduct($product, $validated, $request);

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.product_updated_successfully', data: [
                'product_id' => $result['product']->id,
                'product_uuid' => $result['product']->uuid,
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.product_not_found', data: []);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: collect($e->errors())->flatten()->first() ?? __('labels.validation_failed'),
                data: $e->errors(),
                status: 422,
            );
        } catch (\Exception $e) {
            Log::error('Error while creating product =>'.$e->getMessage());

            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.failed_to_update_product: '.$e->getMessage(), data: []);
        }
    }

    /**
     * Get product pricing data for a specific product
     */
    public function getProductPricing(string $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

            $this->authorize('view', $product);

            $variants = ProductVariant::where('product_id', $id)
                ->with([
                    'attributes.attribute:id,title',
                    'attributes.attributeValue:id,title',
                    'locationInventories.storeLocation.store:id,name',
                    'media',
                ])
                ->get();

            $variantPricing = [];

            foreach ($variants as $variant) {
                $stockLocations = $variant->locationInventories
                    ->map(function ($item) {
                        return [
                            'store_name' => $item->storeLocation?->store?->name ?? '',
                            'store_location_name' => $item->storeLocation?->name ?? '',
                            'tracked' => (bool) $item->tracked,
                            'stock' => (int) $item->stock,
                            'available_stock' => max(0, (int) $item->stock - (int) $item->reserved),
                            'selling' => (bool) $item->is_fulfillment_location
                                && (! $item->tracked || (int) $item->stock > (int) $item->reserved),
                        ];
                    });
                $fulfillmentInventory = $variant->locationInventories->where('is_fulfillment_location', true);
                $hasUnlimitedStock = $fulfillmentInventory->contains(fn ($item) => ! $item->tracked);
                $availableStock = $hasUnlimitedStock
                    ? null
                    : $fulfillmentInventory->sum(fn ($item) => max(0, (int) $item->stock - (int) $item->reserved));

                $variantPricing[$variant->id] = [
                    'variant_id' => $variant->id,
                    'title' => $variant->title,
                    'image' => $variant->image,
                    'sku' => $variant->sku,
                    'availability' => (bool) $variant->availability,
                    'price' => (PricePresenter::forCurrent((float) $variant->price_exclude_tax)['formatted']),
                    'special_price' => $variant->special_price_exclude_tax !== null && $variant->special_price_exclude_tax > 0
                        ? PricePresenter::forCurrent((float) $variant->special_price_exclude_tax)['formatted']
                        : null,
                    'available_stock' => $availableStock,
                    'has_unlimited_stock' => $hasUnlimitedStock,
                    'attributes' => $variant->attributes->map(fn ($attribute) => [
                        'name' => $attribute->attribute?->title,
                        'value' => $attribute->attributeValue?->title,
                    ])->filter(fn ($attribute) => $attribute['name'] && $attribute['value'])->values(),
                    'stock_locations' => $stockLocations,
                    'manage_url' => route($this->panelView('products.edit'), ['id' => $product->id]).'#section-pricing',
                ];
            }

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.product_pricing_fetched_successfully', data: [
                'product_id' => $product->id,
                'variant_pricing' => $variantPricing,
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.failed_to_fetch_product_pricing: '.$e->getMessage(), data: []);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

            $this->authorize('delete', $product);

            if ($product->hasPendingOrders()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.product_cannot_be_deleted_pending_orders_exist',
                    data: []
                );
            }

            $sellerId = $product->seller_id;
            $product->delete();

            if ($sellerId) {
                $this->reduceUsageIfMultivendor((int) $sellerId, SubscriptionPlanKeyEnum::PRODUCT_LIMIT());
            }

            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.product_deleted_successfully', data: []);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.product_not_found', data: []);
        }
    }

    /**
     * Get products for datatable
     */
    public function getProducts(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Product::class);

            [$draw, $start, $length, $searchValue, $filters, $orderColumn, $orderDirection] = $this->extractRequestParams($request);

            $query = $this->buildBaseQuery();

            $totalRecords = $query->count();

            $query = $this->applyFilters($query, $searchValue, $filters);
            $this->applyDatatableDateRange($query, $request, 'products.created_at');
            $hasFilters = $searchValue !== '' || array_filter($filters, fn ($value) => ! empty($value)) !== []
                || $request->filled('date_from') || $request->filled('date_to');
            $filteredRecords = $hasFilters ? $query->count() : $totalRecords;

            $products = $query
                ->orderBy($orderColumn, $orderDirection)
                ->skip($start)
                ->take($length)
                ->get();

            $data = $products->map(fn ($product) => $this->formatProductData($product))->toArray();

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.failed_to_fetch_products: '.$e->getMessage(), []);
        }
    }

    private function extractRequestParams(Request $request): array
    {
        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $filters = [
            'product_type' => $request->get('product_type'),
            'product_status' => $request->get('product_status'),
            'verification_status' => $request->get('verification_status'),
            'product_filter' => $request->get('product_filter'),
            'badge_id' => $request->get('badge_id'),
            'category_id' => $request->get('category_id'),
            'brand_id' => $request->get('brand_id'),
            'store_id' => $this->getPanel() === 'seller'
                ? app(SellerStoreService::class)->selectedId()
                : $request->get('store_id'),
            'seller_id' => $request->get('seller_id'),
        ];

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = strtolower((string) ($request->get('order')[0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $columns = [null, 'id', 'title', 'verification_status', 'updated_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? null ?: 'id';

        return [$draw, $start, $length, $searchValue, $filters, $orderColumn, $orderDirection];
    }

    private function buildBaseQuery(): Builder
    {
        $query = Product::with(['category', 'seller.user', 'badge', 'brand', 'media'])
            ->withCount('variants')
            ->addSelect([
                'variants_min_price' => ProductVariant::query()
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->selectRaw('MIN(product_variants.price)'),
                'variants_min_sell_price' => ProductVariant::query()
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->selectRaw('MIN(CASE WHEN product_variants.special_price > 0 AND product_variants.special_price < product_variants.price THEN product_variants.special_price ELSE product_variants.price END)'),
                'variants_max_price' => ProductVariant::query()
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->selectRaw('MAX(product_variants.price)'),
                'has_store_pricing' => StoreProductVariant::query()
                    ->join('product_variants', 'product_variants.id', '=', 'store_product_variants.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->selectRaw('1')
                    ->limit(1),
                'tracked_stock' => StoreLocationInventory::query()
                    ->join('product_variants', 'product_variants.id', '=', 'store_location_inventory.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->whereNull('product_variants.deleted_at')
                    ->where('store_location_inventory.is_fulfillment_location', true)
                    ->where('store_location_inventory.tracked', true)
                    ->selectRaw('COALESCE(SUM(CASE WHEN store_location_inventory.stock > store_location_inventory.reserved THEN store_location_inventory.stock - store_location_inventory.reserved ELSE 0 END), 0)'),
                'has_untracked_inventory' => StoreLocationInventory::query()
                    ->join('product_variants', 'product_variants.id', '=', 'store_location_inventory.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->whereNull('product_variants.deleted_at')
                    ->where('store_location_inventory.is_fulfillment_location', true)
                    ->where('store_location_inventory.tracked', false)
                    ->selectRaw('1')
                    ->limit(1),
                'inventory_location_count' => StoreLocationInventory::query()
                    ->join('product_variants', 'product_variants.id', '=', 'store_location_inventory.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->whereNull('product_variants.deleted_at')
                    ->where('store_location_inventory.is_fulfillment_location', true)
                    ->selectRaw('COUNT(*)'),
            ]);

        if ($this->getPanel() === 'seller') {
            $query->where('seller_id', $this->sellerId);
        }

        return $query;
    }

    private function applyFilters($query, string $searchValue, array $filters)
    {
        $searchValue = trim($searchValue);
        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('title', 'like', "%{$searchValue}%")
                    ->orWhere('id', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhere('type', 'like', "%{$searchValue}%")
                    ->orWhere('verification_status', 'like', "%{$searchValue}%")
                    ->orWhereHas('category', fn ($q) => $q->where('title', 'like', "%{$searchValue}%"))
                    ->orWhereHas('brand', fn ($q) => $q->where('title', 'like', "%{$searchValue}%"))
                    ->orWhereHas('seller.user', fn ($q) => $q->where('name', 'like', "%{$searchValue}%")->orWhere('email', 'like', "%{$searchValue}%"))
                    ->orWhereHas('variants', fn ($q) => $q->where('sku', 'like', "%{$searchValue}%")->orWhere('barcode', 'like', "%{$searchValue}%"));
            });
        }

        if (! empty($filters['product_type'])) {
            $query->where('type', $filters['product_type']);
        }

        if (! empty($filters['product_status'])) {
            $query->where('status', $filters['product_status']);
        }

        if (! empty($filters['verification_status'])) {
            $status = $filters['verification_status'];
            if ($status === 'pending') {
                $status = ProductVarificationStatusEnum::PENDING();
            }
            $query->where('verification_status', $status);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (! empty($filters['badge_id'])) {
            $query->where('badge_id', (int) $filters['badge_id']);
        }

        if (! empty($filters['store_id'])) {
            $storeId = $filters['store_id'];
            if ($this->getPanel() === 'seller') {
                $query->where(function ($q) use ($storeId) {
                    $q->whereHas('variants.storeProductVariants', fn ($sq) => $sq->where('store_id', $storeId))
                        ->orWhereDoesntHave('variants.storeProductVariants');
                });
            } else {
                $query->whereHas('variants.storeProductVariants', fn ($q) => $q->where('store_id', $storeId));
            }
        }

        if (! empty($filters['seller_id']) && $this->getPanel() === 'admin') {
            $query->where('seller_id', $filters['seller_id']);
        }

        if (! empty($filters['product_filter'])) {
            $query->applyProductFilter($filters['product_filter']);
        }

        return $query;
    }

    private function formatProductData(Product $product): array
    {
        $kitInventory = null;
        if ($product->type === 'kit') {
            $product->loadMissing('variants');
            $kitVariant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();
            if ($kitVariant) {
                $kitVariant->setRelation('product', $product);
                $kitInventory = [
                    'stock' => $kitVariant->stockForStore(),
                    'has_unlimited_inventory' => false,
                ];
            }
        }
        $status = view('partials.status', ['status' => $product->status ?? ''])->render();
        $approvalStatus = view('partials.status', ['status' => $product->verification_status ?? ''])->render();
        if ($this->getPanel() === 'seller'
            && (string) $product->verification_status === (string) ProductVarificationStatusEnum::REJECTED()
            && filled($product->rejection_reason)
        ) {
            $approvalStatus .= '<div class="text-danger small mt-1 text-wrap"><strong>'
                .e(__('labels.rejection_reason')).':</strong> '
                .e($product->rejection_reason).'</div>';
        }

        return [
            'select' => '<input type="checkbox" class="form-check-input product-row-checkbox" value="'.$product->id.'">',
            'id' => $product->id,
            'product_details' => view('partials.product-list-item', [
                'product' => $product,
                'kitInventory' => $kitInventory,
                'status' => $status,
                'viewRoute' => route($this->panelView('products.show'), ['id' => $product->id]),
                'showSeller' => $this->getPanel() === 'admin',
            ])->render(),
            'admin_approval_status' => $approvalStatus,
            'updated_at' => '<span title="'.e($product->updated_at->format('Y-m-d H:i')).'">'
                .e($product->updated_at->diffForHumans()).'</span>',
            'action' => view('partials.product-actions', [
                'modelName' => 'product',
                'id' => $product->id,
                'title' => $product->title,
                'status' => $product->status,
                'badge' => $product->badge,
                'showBadgeAction' => $this->getPanel() === 'admin',
                'mode' => 'page_view',
                'route' => route($this->panelView('products.edit'), ['id' => $product->id]),
                'viewRoute' => route($this->panelView('products.show'), ['id' => $product->id]),
                'editPermission' => $this->editPermission,
                'deletePermission' => $this->deletePermission,
                'viewPermission' => $this->viewPermission,
            ])->render(),
        ];
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('search');
        $exceptId = $request->input('exceptId');
        $findId = $request->input('find_id');
        $sellerId = $request->input('seller_id');
        $categoryId = $request->integer('category_id');

        if ($findId) {
            $products = Product::where('id', $findId)
                ->when($this->getPanel() === 'seller', fn ($q) => $q->where('seller_id', $this->sellerId))
                ->select('id', 'title', 'brand_id', 'badge_id', 'image_fit', 'is_inclusive_tax')
                ->with(['media', 'variants:id,product_id,price,special_price,is_default', 'brand:id,title', 'badge:id,label,bg_color,text_color,border_color'])
                ->get();
        } else {
            $products = Product::select('id', 'title', 'brand_id', 'badge_id', 'image_fit', 'is_inclusive_tax')
                ->where('title', 'LIKE', "%{$query}%")
                ->when($exceptId, function ($q) use ($exceptId) {
                    $q->where('id', '!=', $exceptId);
                })
                ->when($categoryId > 0, function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId);
                })
                ->when($this->getPanel() === 'seller', function ($q) {
                    $q->where('seller_id', $this->sellerId);
                })
                ->when($this->getPanel() === 'admin' && $sellerId, function ($q) use ($sellerId) {
                    $q->where('seller_id', $sellerId);
                })
                ->with(['media', 'variants:id,product_id,price,special_price,is_default', 'brand:id,title', 'badge:id,label,bg_color,text_color,border_color'])
                ->limit(10)
                ->get();
        }
        $results = $products->map(function ($product) {
            $variant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();
            $price = (float) ($variant?->price ?? 0);
            $specialPrice = (float) ($variant?->special_price ?? 0);
            $hasDiscount = $specialPrice > 0 && $specialPrice < $price;

            return [
                'id' => $product->id,
                'value' => $product->id,
                'text' => $product->title,
                'image_url' => $product->main_image,
                'preview' => [
                    'brand' => $product->brand?->title,
                    'price' => $price,
                    'special_price' => $specialPrice,
                    'formatted_price' => app(CurrencyService::class)->format($price),
                    'formatted_special_price' => $hasDiscount ? app(CurrencyService::class)->format($specialPrice) : null,
                    'discount_percent' => $hasDiscount && $price > 0 ? (int) round((($price - $specialPrice) / $price) * 100) : 0,
                    'image_fit' => $product->image_fit ?: 'contain',
                    'badge' => $product->badge ? [
                        'label' => $product->badge->label,
                        'bg_color' => $product->badge->bg_color,
                        'text_color' => $product->badge->text_color,
                        'border_color' => $product->badge->border_color,
                    ] : null,
                ],
            ];
        });

        return response()->json($results);
    }

    /**
     * Return attributes for a given seller (AJAX, admin panel only).
     */
    public function getAttributesBySeller(Request $request): JsonResponse
    {
        $sellerId = (int) $request->input('seller_id', 0);
        if ($sellerId <= 0) {
            return response()->json([]);
        }

        $attributes = GlobalAttributeService::getAttributesWithValue($sellerId);

        return response()->json($attributes);
    }

    /**
     * Tax-class options for the product form's Tax multi-select, scoped to
     * the markets the picked seller's stores are enabled in. Called by the
     * admin form when the seller picker changes — the server-rendered options
     * belong to the previously selected seller and must be replaced.
     *
     * Also serves as the typeahead source for the same multi-select (search
     * filters by title within the seller's market-scoped tax classes), so the
     * global /admin/tax-classes/search endpoint isn't used here — that one
     * doesn't honour market scope and would leak other sellers' tax classes.
     *
     * Response: [{ value: <tax_class_id>, text: "<title> (<market>)" }, ...]
     */
    public function getTaxClassesBySeller(Request $request): JsonResponse
    {
        $sellerId = (int) $request->input('seller_id', 0);
        if ($sellerId <= 0) {
            return response()->json([]);
        }

        $search = trim((string) ($request->input('search') ?? $request->input('q') ?? ''));

        $markets = $this->resolveSellerMarketsWithTaxClasses($sellerId);

        $options = [];
        foreach ($markets as $market) {
            $pricePreview = $market->price_preview;
            foreach ($market->taxClasses as $tc) {
                if ($search !== '' && stripos($tc->title, $search) === false) {
                    continue;
                }
                $options[] = [
                    'value' => (string) $tc->id,
                    'text' => $tc->title.' ('.$market->name.')',
                    'market' => $market->name,
                    'rate' => (float) $tc->taxRates->sum('rate'),
                    'currency_code' => $pricePreview['currency_code'],
                    'currency_symbol' => $pricePreview['currency_symbol'],
                    'fx_rate' => $pricePreview['fx_rate'],
                    'fx_fallback' => (bool) ($pricePreview['fx_fallback'] ?? false),
                    'currency_format' => $pricePreview['format'],
                ];
            }
        }

        return response()->json($options);
    }

    public function updateVerificationStatus(Request $request, int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $this->authorize('verifyProduct', $product);

            $request->validate([
                'verification_status' => ['required', new Enum(ProductVarificationStatusEnum::class)],
                'rejection_reason' => 'required_if:verification_status,'.ProductVarificationStatusEnum::REJECTED().'|max:500',
            ]);

            $status = $request->input('verification_status');
            if ($status === ProductVarificationStatusEnum::REJECTED() && empty($request->input('rejection_reason'))) {
                return ApiResponseType::sendJsonResponse(success: false, message: 'Rejection reason is required', data: []);
            }

            $from = (string) $product->verification_status;

            if ($from === (string) $status) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.verification_status_unchanged'),
                    data: []
                );
            }

            $product->verification_status = $status;
            $newStatus = $status === ProductVarificationStatusEnum::APPROVED()
                ? ProductStatusEnum::ACTIVE()
                : ProductStatusEnum::DRAFT();

            $product->status = $newStatus;
            $product->rejection_reason = $status === ProductVarificationStatusEnum::REJECTED()
                ? $request->input('rejection_reason')
                : null;
            $product->save();

            ProductModerationLog::create([
                'product_id' => $product->id,
                'admin_id' => auth()->id(),
                'from_status' => $from,
                'to_status' => (string) $status,
                'reason' => $product->rejection_reason,
            ]);

            return ApiResponseType::sendJsonResponse(success: true, message: 'Verification status updated successfully', data: [
                'id' => $product->id,
                'verification_status' => $product->verification_status,
                'rejection_reason' => $product->rejection_reason,
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.product_not_found', data: []);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: $e->getMessage(), data: $e->errors());
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.something_went_wrong', data: ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update product status (draft/active)
     */
    public function updateStatus(int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $this->authorize('update', $product);

            $newStatus = $product->status === ProductStatusEnum::ACTIVE()
                ? ProductStatusEnum::DRAFT()
                : ProductStatusEnum::ACTIVE();

            if ((string) $newStatus === (string) ProductStatusEnum::ACTIVE()
                && (string) $product->verification_status !== (string) ProductVarificationStatusEnum::APPROVED()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.product_not_approved_cannot_activate'),
                    data: [],
                    status: 422
                );
            }

            $product->status = $newStatus;
            $product->save();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: "Product status updated to {$newStatus} successfully",
                data: [
                    'id' => $product->id,
                    'status' => $product->status,
                ]
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.product_not_found', data: []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.something_went_wrong', data: ['error' => $e->getMessage()]);
        }
    }
}
