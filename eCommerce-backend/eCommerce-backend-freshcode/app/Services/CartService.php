<?php

namespace App\Services;

use App\Enums\Addon\AddonGroupSelectionTypeEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PromoDiscountTypeEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\PromoModeEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Events\Cart\ItemAddedToCart;
use App\Events\Cart\ItemRemovedFromCart;
use App\Http\Resources\Product\ProductListResource;
use App\Models\Cart;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\CartItemAddon;
use App\Models\Market;
use App\Models\Order;
use App\Models\OrderPromoLine;
use App\Models\Promo;
use App\Models\StoreProductVariant;
use App\Models\StoreProductVariantAddon;
use App\Models\User;
use App\Services\Fx\FxRateService;
use App\Support\PricePresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CartService
{
    public function __construct(
        protected ?StoreAddonItemService $storeAddonItemService = null,
    )
    {
        $this->storeAddonItemService ??= app(StoreAddonItemService::class);
    }

    /**
     * Add item to cart
     *
     * Supports an optional `addons` array on $data:
     *   ['addons' => [['addon_group_id' => 1, 'addon_item_id' => 10], ...]]
     *
     * When present, the selections are validated against
     * `store_product_variant_addons` (is the item attached to the variant at
     * this store?), the per-group rules (SINGLE/MULTIPLE, is_required) are
     * enforced, and per-store pricing is snapshotted into `cart_item_addons`.
     * Two add calls with the same (product, variant, store) but different
     * addon sets create separate cart lines; same set → qty merges.
     */
    public function addToCart(User $user, array $data): array
    {
        try {
            $storeProductVariant = $this->getStoreProductVariant((int)$data['store_id'], (int)$data['product_variant_id']);
            if (!$storeProductVariant || empty($storeProductVariant['productVariant'])) {
                return [
                    'success' => false,
                    'message' => __('messages.product_variant_not_available_in_store'),
                    'data' => [],
                ];
            }

            if ($this->isStoreOffline($storeProductVariant)) {
                return [
                    'success' => false,
                    'message' => __('messages.store_offline_cannot_add_to_cart'),
                    'data' => ['store_id' => $storeProductVariant->store?->id],
                ];
            }

            $addonResolution = $this->resolveAddonSelections(
                storeId: (int)$data['store_id'],
                productVariantId: (int)$data['product_variant_id'],
                addons: is_array($data['addons'] ?? null) ? $data['addons'] : [],
            );
            if (!$addonResolution['success']) {
                return $addonResolution['error'];
            }
            $resolvedAddons = $addonResolution['resolved'];
            $addonSignature = CartItem::buildAddonSignature(
                array_column($resolvedAddons, 'addon_item_id')
            );

            $cart = Cart::firstOrCreate(
                ['user_id' => $user?->id],
                ['uuid' => Str::uuid()->toString()]
            );

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $storeProductVariant['productVariant']['product_id'])
                ->where('product_variant_id', $data['product_variant_id'])
                ->where('store_id', $data['store_id'])
                ->where(function ($q) use ($addonSignature) {
                    if ($addonSignature === null) {
                        $q->whereNull('addon_signature');
                    } else {
                        $q->where('addon_signature', $addonSignature);
                    }
                })
                ->first();
            $userCart = $this->getUserCart($user);

            $singleStoreValidation = $this->validateSingleStoreCheckout($userCart, (int)$data['store_id']);
            if ($singleStoreValidation !== null) {
                return $singleStoreValidation;
            }

            $requestedQuantity = $data['quantity'] ?? 1;
            $replaceQuantity = (bool)($data['replace_quantity'] ?? false);
            return DB::transaction(function () use (
                $addonSignature,
                $cart,
                $cartItem,
                $data,
                $replaceQuantity,
                $requestedQuantity,
                $resolvedAddons,
                $storeProductVariant,
                $user,
                $userCart,
            ) {
                if ($cartItem) {
                    return $this->updateExistingItem($cartItem, $userCart, $storeProductVariant, $resolvedAddons, $requestedQuantity, $replaceQuantity, $cart, $user);
                }

                return $this->createNewItem($cart, $data, $userCart, $storeProductVariant, $resolvedAddons, $requestedQuantity, $addonSignature, $user);
            });

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }
    private function checkMaxItems($userCart): ?array
    {
        if ($this->validateCartMaxItems($userCart)) return null;

        return [
            'success' => false,
            'message' => __('messages.maximum_items_allowed_in_cart_reached'),
            'data' => [],
        ];
    }

    private function validateQuantity($quantity, $rules, $stock, $addons): ?array
    {
        return $this->validateQuantityRules($quantity, $rules)
            ?? $this->checkStock($quantity, $stock, (int)($rules['max'] ?? 0))
            ?? $this->checkAddonsStock($addons, $quantity);
    }

    private function updateExistingItem($cartItem, $userCart, $storeProductVariant, $resolvedAddons, $requestedQuantity, $replaceQuantity, $cart, $user): array
    {
        if ($cartItem->save_for_later) {
            if ($error = $this->checkMaxItems($userCart)) return $error;
            $cartItem->update(['save_for_later' => '0']);
        }

        $rules = $this->extractQuantityRules($storeProductVariant);
        $increment = $requestedQuantity > 0 ? $requestedQuantity : (int) ($rules['step'] ?? 1);
        $newQuantity = $replaceQuantity
            ? $requestedQuantity
            : $cartItem->quantity + $increment;

        if ($error = $this->validateQuantity($newQuantity, $rules, $storeProductVariant->stock, $resolvedAddons)) {
            return $error;
        }

        $cartItem->update(['quantity' => $newQuantity]);

        return $this->finalizeCart($cart, $cartItem, $user);
    }

    private function createNewItem($cart, $data, $userCart, $storeProductVariant, $resolvedAddons, $requestedQuantity, $addonSignature, $user): array
    {
        $rules = $this->extractQuantityRules($storeProductVariant);

        if ($error = $this->validateQuantity($requestedQuantity, $rules, $storeProductVariant->stock, $resolvedAddons)) {
            return $error;
        }

        if ($error = $this->checkMaxItems($userCart)) return $error;

        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $storeProductVariant['productVariant']['product_id'],
            'product_variant_id' => $data['product_variant_id'],
            'store_id' => $data['store_id'],
            'quantity' => $requestedQuantity,
            'addon_signature' => $addonSignature,
            'save_for_later' => '0',
        ]);

        $this->persistCartItemAddons($cartItem, $resolvedAddons);

        return $this->finalizeCart($cart, $cartItem, $user);
    }

    private function finalizeCart($cart, $cartItem, $user): array
    {
        $cart->load(['items.product', 'items.variant', 'items.store', 'items.addons']);
        event(new ItemAddedToCart($cart, $cartItem, $user));

        return [
            'success' => true,
            'message' => __('messages.item_added_to_cart_successfully'),
            'data' => $cart,
        ];
    }

    /**
     * Validate the submitted addon selections against the variant's
     * `store_product_variant_addons` attachments and resolve the store-level
     * price snapshot for each one.
     *
     * Returns either:
     *   ['success' => true, 'resolved' => [['addon_group_id' => …, 'addon_item_id' => …, 'price' => …, 'stock' => …], ...]]
     * or:
     *   ['success' => false, 'error' => <standard service error response>]
     *
     * Each resolved row carries the store-level `stock` value (nullable — `null`
     * when the addon falls back to the catalog, since the catalog doesn't
     * track stock). Callers are expected to validate stock against the final
     * cart-line quantity via {@see checkAddonsStock()} once that quantity is known.
     *
     * Semantics enforced:
     *  - every submitted (group, item) pair must be attached to the variant at this store
     *  - SINGLE groups may not carry more than one item
     *  - every required group attached to the variant must have a selection
     *  - the addon must be available (store_addon_items.is_available when the
     *    per-store row exists; fallback to catalog addon_items.is_available)
     *
     * @param array<int,array{addon_group_id:int|string,addon_item_id:int|string}> $addons
     */
    private function resolveAddonSelections(int $storeId, int $productVariantId, array $addons): array
    {
        $attached = StoreProductVariantAddon::with(['addonGroup', 'addonItem'])
            ->where('store_id', $storeId)
            ->where('product_variant_id', $productVariantId)
            ->get();

        $pairs = collect($addons)
            ->map(fn($a) => [
                'addon_group_id' => (int)($a['addon_group_id'] ?? 0),
                'addon_item_id' => (int)($a['addon_item_id'] ?? 0),
            ])
            ->filter(fn($p) => $p['addon_group_id'] > 0 && $p['addon_item_id'] > 0)
            ->unique(fn($p) => $p['addon_group_id'] . ':' . $p['addon_item_id'])
            ->values();

        $attachedMap = $attached->keyBy(
            fn($row) => ((int)$row->addon_group_id) . ':' . ((int)$row->addon_item_id)
        );

        foreach ($pairs as $pair) {
            $key = $pair['addon_group_id'] . ':' . $pair['addon_item_id'];
            if (!$attachedMap->has($key)) {
                return [
                    'success' => false,
                    'error' => [
                        'success' => false,
                        'message' => __('messages.addon_not_available_for_variant'),
                        'data' => [
                            'addon_group_id' => $pair['addon_group_id'],
                            'addon_item_id' => $pair['addon_item_id'],
                        ],
                    ],
                ];
            }
        }

        $byGroup = $pairs->groupBy('addon_group_id');
        $groupsIndexed = $attached->pluck('addonGroup')->filter()->unique('id')->keyBy('id');

        foreach ($byGroup as $groupId => $groupPairs) {
            $group = $groupsIndexed->get((int)$groupId);
            if (!$group) {
                continue;
            }
            $selectionType = $group->selection_type;
            $selType = $selectionType instanceof AddonGroupSelectionTypeEnum
                ? $selectionType
                : (is_string($selectionType) ? AddonGroupSelectionTypeEnum::tryFrom($selectionType) : null);

            if ($selType === AddonGroupSelectionTypeEnum::SINGLE && $groupPairs->count() > 1) {
                return [
                    'success' => false,
                    'error' => [
                        'success' => false,
                        'message' => __('messages.addon_group_single_selection_required', ['group' => $group->title]),
                        'data' => ['addon_group_id' => (int)$groupId],
                    ],
                ];
            }
        }

        foreach ($groupsIndexed as $gid => $group) {
            if ((bool)$group->is_required && !$byGroup->has((int)$gid)) {
                return [
                    'success' => false,
                    'error' => [
                        'success' => false,
                        'message' => __('messages.addon_group_required_missing', ['group' => $group->title]),
                        'data' => ['addon_group_id' => (int)$gid],
                    ],
                ];
            }
        }

        if ($pairs->isEmpty()) {
            return ['success' => true, 'resolved' => []];
        }

        $resolved = [];
        foreach ($pairs as $pair) {
            $attachment = $attachedMap->get($pair['addon_group_id'] . ':' . $pair['addon_item_id']);
            $inventory = $this->storeAddonItemService->findForCheckout($storeId, $pair['addon_item_id']);

            if ($inventory) {
                if (!$inventory->is_available) {
                    return [
                        'success' => false,
                        'error' => [
                            'success' => false,
                            'message' => __('messages.addon_item_unavailable'),
                            'data' => ['addon_item_id' => $pair['addon_item_id']],
                        ],
                    ];
                }
                $price = (float)$inventory->price;
                $stock = (int)$inventory->stock;
            } else {
                $catalog = $attachment?->addonItem;
                if (!$catalog || !(bool)($catalog->is_available ?? true)) {
                    return [
                        'success' => false,
                        'error' => [
                            'success' => false,
                            'message' => __('messages.addon_item_unavailable'),
                            'data' => ['addon_item_id' => $pair['addon_item_id']],
                        ],
                    ];
                }
                $price = (float)($catalog->price ?? 0);
                $stock = null;
            }

            $resolved[] = [
                'addon_group_id' => $pair['addon_group_id'],
                'addon_item_id' => $pair['addon_item_id'],
                'price' => $price,
                'stock' => $stock,
            ];
        }

        return ['success' => true, 'resolved' => $resolved];
    }

    /**
     * Ensure there is enough per-store stock for every resolved addon given
     *
     * @param array<int,array{addon_group_id:int,addon_item_id:int,price:float,stock:int|null}> $resolvedAddons
     */
    private function checkAddonsStock(array $resolvedAddons, int $quantity): ?array
    {
        foreach ($resolvedAddons as $addon) {
            $stock = $addon['stock'] ?? null;
            if ($stock === null || $stock < $quantity) {
                return [
                    'success' => false,
                    'message' => __('messages.addon_item_insufficient_stock'),
                    'data' => [
                        'addon_item_id' => $addon['addon_item_id'],
                        'available_stock' => (int)$stock,
                        'requested' => $quantity,
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Re-validate every addon snapshot on a cart against the current store
     * catalog + inventory. This is the place-order equivalent of the checks
     * {@see resolveAddonSelections()} performs when a user adds to cart — we
     * run them again because (a) the cart snapshot may be stale, (b) the
     * seller may have toggled availability or detached addons since, and
     * (c) another order may have consumed the addon stock the user is
     * relying on.
     *
     * Returns `null` on success, or a standard service error envelope on the
     * first failed check (same shape as `addToCart` errors so OrderService
     * can forward it verbatim).
     */
    public function validateCartAddonsForCheckout(Cart $cart): ?array
    {
        foreach ($cart->items as $cartItem) {
            if ($cartItem->save_for_later === true || (string)$cartItem->save_for_later === '1') {
                continue;
            }

            $existingAddons = $cartItem->relationLoaded('addons')
                ? $cartItem->getRelation('addons')
                : $cartItem->addons()->get();

            $payload = $existingAddons->map(fn(CartItemAddon $addon) => [
                'addon_group_id' => (int)$addon->addon_group_id,
                'addon_item_id' => (int)$addon->addon_item_id,
            ])->all();

            $resolution = $this->resolveAddonSelections(
                storeId: (int)$cartItem->store_id,
                productVariantId: (int)$cartItem->product_variant_id,
                addons: $payload,
            );
            if (!$resolution['success']) {
                return $resolution['error'];
            }

            if ($error = $this->checkAddonsStock($resolution['resolved'], (int)$cartItem->quantity)) {
                return $error;
            }
        }

        return null;
    }

    /**
     * Stock check for the addons already attached to a cart line — used on
     * quantity updates that don't pass a fresh addons' payload. Pulls the
     * per-store inventory for each existing `cart_item_addons` row and
     * enforces `stock >= quantity` when a `store_addon_items` row exists.
     * Catalog-only addons (no per-store row) are skipped.
     */
    private function checkExistingAddonsStock(CartItem $cartItem, int $quantity): ?array
    {
        $existing = $cartItem->addons()->get();

        if ($existing->isEmpty()) {
            return null;
        }

        $resolved = $existing->map(function (CartItemAddon $addon) use ($cartItem) {
            $inventory = $this->storeAddonItemService->findForCheckout(
                (int)$cartItem->store_id,
                (int)$addon->addon_item_id,
            );

            return [
                'addon_group_id' => (int)$addon->addon_group_id,
                'addon_item_id' => (int)$addon->addon_item_id,
                'price' => (float)$addon->price,
                'stock' => $inventory ? (int)$inventory->stock : null,
            ];
        })->all();

        return $this->checkAddonsStock($resolved, $quantity);
    }

    /**
     * Persist resolved addon selections against a cart line.
     *
     * @param array<int,array{addon_group_id:int,addon_item_id:int,price:float}> $resolvedAddons
     */
    private function persistCartItemAddons(CartItem $cartItem, array $resolvedAddons): void
    {
        $cartItem->addons()->delete();

        foreach ($resolvedAddons as $addon) {
            CartItemAddon::create([
                'uuid' => (string)Str::uuid(),
                'cart_item_id' => $cartItem->id,
                'addon_group_id' => $addon['addon_group_id'],
                'addon_item_id' => $addon['addon_item_id'],
                'price' => $addon['price'],
            ]);
        }
    }

    /**
     * Resolve the (store, variant) mapping row. The old `where('stock', '>', 0)`
     * filter is gone — stock now lives on store_location_inventory and is
     * evaluated per-location at fulfillment time. Availability for "can it go
     * into the cart at all" is the `is_in_stock` accessor on the mapping,
     * which sums tracked rows across the store's fulfillment locations.
     */
    private function getStoreProductVariant(int $storeId, int $productVariantId): ?StoreProductVariant
    {
        return StoreProductVariant::where('store_id', $storeId)
            ->where('product_variant_id', $productVariantId)
            ->whereHas('productVariant.product', function ($query) {
                $query->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->where('status', ProductStatusEnum::ACTIVE());
            })
            ->with(['productVariant', 'store'])
            ->first();
    }

    /**
     * Helper: whether the store backing this variant is not currently visible
     * to customers. The old online/offline gate is gone — visibility_status
     * (visible / draft) is the storefront switch now.
     */
    private function isStoreOffline(StoreProductVariant $storeProductVariant): bool
    {
        $store = $storeProductVariant->store;
        if (!$store) {
            return true;
        }
        $vis = $store->visibility_status;
        $value = $vis instanceof \BackedEnum ? $vis->value : (string)($vis ?? '');
        return $value !== StoreVisibilityStatusEnum::VISIBLE();
    }

    /**
     * Helper: Validate single-store checkout rule. Returns error response array on failure, null on success.
     */
    private function validateSingleStoreCheckout(?Cart $userCart, int $incomingStoreId): ?array
    {
        try {
            $settingService = app(SettingService::class);
            $settings = $settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
            $checkoutType = $settings->value['checkoutType'] ?? null;

            if ($checkoutType === 'single_store') {
                $existingStoreIds = collect($userCart?->items ?? [])->pluck('store_id')->filter()->unique();
                if ($existingStoreIds->count() > 0 && !$existingStoreIds->contains($incomingStoreId)) {
                    return [
                        'success' => false,
                        'message' => __('labels.checkout_type_single_store_error'),
                        'data' => [],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Checkout type validation failed while adding to cart', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Helper: Extract quantity rules from product config with sane defaults.
     * Returns ['min' => int, 'step' => int, 'max' => int]
     */
    private function extractQuantityRules(StoreProductVariant $spv): array
    {
        $product = $spv->productVariant->product ?? null;

        return [
            'min' => (int)($product->minimum_order_quantity ?? 1),
            'step' => (int)($product->quantity_step_size ?? 1),
            'max' => (int)($product->total_allowed_quantity ?? 0),
        ];
    }

    /**
     * Helper: Validate quantity against rules. Returns error response array on failure, null on success.
     */
    private function validateQuantityRules(int $quantity, array $rules): ?array
    {
        $minQty = (int)($rules['min'] ?? 1);
        $stepSize = (int)($rules['step'] ?? 1);
        $maxTotal = (int)($rules['max'] ?? 0);

        if ($quantity < $minQty) {
            return [
                'success' => false,
                'message' => __('messages.quantity_must_be_at_least_minimum_order_quantity', ['min' => $minQty]),
                'data' => [
                    'minimum_order_quantity' => $minQty,
                    'attempted_quantity' => $quantity,
                ],
            ];
        }

        if ($maxTotal > 0 && $quantity > $maxTotal) {
            return [
                'success' => false,
                'message' => __('messages.quantity_must_not_exceed_total_allowed_quantity', ['max' => $maxTotal]),
                'data' => [
                    'total_allowed_quantity' => $maxTotal,
                    'attempted_quantity' => $quantity,
                ],
            ];
        }

        if ($stepSize > 1 && (($quantity - $minQty) % $stepSize) !== 0) {
            return [
                'success' => false,
                'message' => __('messages.quantity_must_match_step_from_minimum', [
                    'min' => $minQty,
                    'step' => $stepSize,
                ]),
                'data' => [
                    'minimum_order_quantity' => $minQty,
                    'step_size' => $stepSize,
                    'attempted_quantity' => $quantity,
                ],
            ];
        }

        return null;
    }

    /**
     * Helper: Ensure requested quantity is within available stock.
     * Pass null for $availableStock to mean "untracked" (always passes — the
     * variant has at least one fulfillment-eligible untracked location).
     * Returns error array on failure, null on success.
     */
    private function checkStock(int $quantity, ?int $availableStock, int $maxTotal = 0): ?array
    {
        if ($availableStock === null) {
            if ($maxTotal <= 0) {
                return [
                    'success' => false,
                    'message' => __('messages.insufficient_stock_available'),
                    'data' => ['available_stock' => 0],
                ];
            }

            return null;
        }
        if ($quantity > $availableStock) {
            return [
                'success' => false,
                'message' => __('messages.insufficient_stock_available'),
                'data' => ['available_stock' => $availableStock],
            ];
        }

        return null;
    }

    public static function validateCartMaxItems($cart, bool $isAdding = true): bool
    {
        $settingService = new SettingService;
        $setting = $settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
        $maxCartItems = (int)($setting->value['maximumItemsAllowedInCart'] ?? 0);

        if ($maxCartItems <= 0) {
            return true;
        }

        $itemCount = $cart->items->count() ?? 0;
        $checkCount = $isAdding ? $itemCount + 1 : $itemCount;

        return $checkCount <= $maxCartItems;
    }

    public function syncMultiStoreCart(User $user, array $data): array
    {
        $synced = [];
        $failed = [];

        DB::beginTransaction();

        try {
            foreach ($data['items'] as $item) {
                $addons = is_array($item['addons'] ?? null) ? $item['addons'] : [];

                $result = $this->addToCart($user, [
                    'store_id' => $item['store_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'quantity' => $item['quantity'],
                    'addons' => $addons,
                    'replace_quantity' => true,
                ]);

                $storeProductVariant = StoreProductVariant::with([
                    'productVariant.product.variants.storeProductVariants.store',
                    'productVariant.product.category',
                    'productVariant.product.brand',
                    'productVariant.product.seller.user',
                    'store',
                    'productVariant',
                ])
                    ->where('store_id', $item['store_id'])
                    ->where('product_variant_id', $item['product_variant_id'])
                    ->first();

                $productResource = null;
                if ($storeProductVariant && $storeProductVariant->productVariant && $storeProductVariant->productVariant->product) {
                    $product = $storeProductVariant->productVariant->product;
                    $productResource = new ProductListResource($product->loadMissing([
                        'variants.storeProductVariants.store',
                        'category',
                        'brand',
                        'seller.user',
                    ]));
                }

                if ($result['success']) {
                    $synced[] = [
                        'store_id' => $item['store_id'],
                        'product_variant_id' => $item['product_variant_id'],
                        'quantity' => $item['quantity'],
                        'addons' => $addons,
                        'product' => $productResource,
                    ];
                } else {
                    $failed[] = [
                        'store_id' => $item['store_id'],
                        'product_variant_id' => $item['product_variant_id'],
                        'addons' => $addons,
                        'product' => $productResource,
                        'reason' => $result['message'],
                        'details' => $result['data'] ?? null,
                    ];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.cart_synced_successfully'),
                'data' => [
                    'synced_items' => $synced,
                    'failed_items' => $failed,
                    'cart' => $this->getUserCart($user),
                ],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Multi-store cart sync failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Calculate cart item prices and totals.
     *
     * Addon selections snapshotted on `cart_item_addons` contribute to every
     * line: one `cart_item_addons` row represents "one unit of this addon per
     * parent cart item", so the per-line addon amount is
     * `quantity × sum(addon.price)`. This is folded into `items_total` so the
     * payment summary (delivery-free threshold, promo min_order_total, promo
     * discount, wallet debit, payable amount) reflects the true line total.
     *
     * Side effects on each cart item:
     *  - `price`         : product unit price × quantity + addon line total
     *  - `special_price` : product special-price × quantity + addon line total (when a special price is configured)
     *  - `addons_total`  : addon line total only (quantity × per-unit addon sum)
     *
     * @param Cart $cart The cart to calculate totals for
     * @return array Array containing items_total and store_ids
     */
    public function calculateCartTotals(Cart $cart): array
    {
        $itemsTotal = 0;
        $totalSaving = 0;
        $storeIds = [];

        foreach ($cart->items as $key => $item) {
            if (empty($item->variant)) {
                $item->delete();

                continue;
            }

            if (!in_array($item->store_id, $storeIds)) {
                $storeIds[] = $item->store_id;
            }

            $storeVariant = $item->variant->storeProductVariants->where('store_id', $item->store->id)->first();

            $addonsPerUnit = $this->sumCartItemAddonPrices($item);
            $quantity = (int)$item->quantity;
            $addonsLineTotal = $quantity * $addonsPerUnit;

            $productLinePrice = $quantity * (float)$storeVariant->price_exclude_tax;
            $productLineSpecialPrice = $quantity * (float)$storeVariant->special_price_exclude_tax;

            $cart->items[$key]->price = $productLinePrice + $addonsLineTotal;
            $cart->items[$key]->special_price = $productLineSpecialPrice > 0
                ? $productLineSpecialPrice + $addonsLineTotal
                : $productLineSpecialPrice;
            $cart->items[$key]->addons_total = $addonsLineTotal;

            $effectiveUnitPrice = $storeVariant->special_price > 0
                ? (float)$storeVariant->special_price
                : (float)$storeVariant->price;

            $itemsTotal += ($quantity * $effectiveUnitPrice) + $addonsLineTotal;

            $regularUnitPrice = (float)$storeVariant->price;
            $specialUnitPrice = (float)$storeVariant->special_price;
            if ($specialUnitPrice > 0 && $specialUnitPrice < $regularUnitPrice) {
                $totalSaving += $quantity * ($regularUnitPrice - $specialUnitPrice);
            }
        }

        $cart->items_total = $itemsTotal;

        return [
            'items_total' => $itemsTotal,
            'total_saving' => $totalSaving,
            'store_ids' => $storeIds,
        ];
    }

    /**
     * Sum the per-unit price snapshot of every addon attached to a cart line.
     * Uses the eager-loaded `addons` relation when available (the cart is
     * loaded with `items.addons.*` throughout this service) and falls back to
     * a lazy query so the method is safe to call in isolation.
     */
    private function sumCartItemAddonPrices(CartItem $item): float
    {
        if ($item->relationLoaded('addons')) {
            return (float)$item->getRelation('addons')->sum(fn($a) => (float)$a->price);
        }

        return (float)$item->addons()->sum('price');
    }

    /**
     * Get user's cart with updated e-commerce location information
     *
     * @param User $user The user whose cart to retrieve
     * @param int|null $addressId Address ID to resolve shipping zone
     * @param bool $useWallet Whether to use wallet balance for payment
     * @param string|null $promoCode Promo code to apply discount
     * @return array Cart data with success status and message
     */
    public function getCart(User $user, ?int $addressId = null, bool $useWallet = false, ?string $promoCode = null, ?string $paymentType = null): array
    {
        try {
            return DB::transaction(function () use ($addressId, $promoCode, $useWallet, $user, $paymentType) {
                $cart = $this->getUserCart($user);
                if (!$cart || $cart->items->isEmpty()) {
                    return [
                        'success' => false,
                        'message' => __('messages.cart_is_empty'),
                        'data' => [],
                    ];
                }

                $address = null;
                if (!empty($addressId)) {
                    $address = Address::where(['user_id' => $user->id, 'id' => $addressId])->first();
                }

                $cart = $this->calculateCartPaymentSummary(
                    cart: $cart,
                    address: $address,
                    useWallet: $useWallet,
                    promoCode: $promoCode,
                    paymentType: $paymentType
                );

                return $this->prepareCartResponse($cart, [], null, $address?->id);
            });

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Get and validate user's cart
     *
     * @param User $user The user whose cart to retrieve
     * @return Cart Result containing success status and cart if found
     */
    public static function getUserCart(User $user): ?Cart
    {
        return Cart::with([
            'items' => function ($query) {
                $query->where('save_for_later', '0');
            },
            'items.product',
            'items.variant',
            'items.variant.attributes.attributeValue',
            'items.store',
            'items.addons.addonGroup',
            'items.addons.addonItem',
            'items.product.taxClasses.taxRates',
            'items.variant.storeProductVariants',
            'user.wallet',
        ])
            ->where('user_id', $user->id)->first();
    }

    public function getSaveForLaterCart(User $user): Cart
    {
        return Cart::with([
            'items' => function ($query) {
                $query->where('save_for_later', '1');
            },
            'items.product',
            'items.variant',
            'items.store',
            'items.addons.addonGroup',
            'items.addons.addonItem',
        ])
            ->where('user_id', $user->id)->first();
    }

    public static function cartStoreCount(User $user): int
    {
        $cart = Cart::with([
            'items' => function ($query) {
                $query->where('save_for_later', '0');
                $query->groupBy('store_id');
            },
        ])
            ->where('user_id', $user->id)->first();

        return $cart->items->count() ?? 0;
    }

    /**
     * Calculate payment summary for the cart
     *
     * @param Cart $cart The cart to calculate payment for
     * @param Address|null $address Address model to resolve shipping zone
     * @param bool $useWallet Whether to use wallet balance for payment
     * @param string|null $promoCode Promo code to apply discount
     * @return Cart Cart with payment summary attached
     */
    private function calculateCartPaymentSummary(Cart $cart, ?Address $address, bool $useWallet, ?string $promoCode = null, ?string $paymentType = null): Cart
    {
        try {
            $paymentSummary = $this->getPaymentSummary(
                cart: $cart,
                address: $address,
                useWallet: $useWallet,
                promoCode: $promoCode,
                paymentType: $paymentType
            );
            $cart->payment_summary = $paymentSummary;
        } catch (\Exception $e) {
            Log::error('Error getting payment summary: ' . $e->getMessage());
            $cart->payment_summary = $this->createDefaultPaymentSummary($cart, $useWallet);
        }

        return $cart;
    }

    /**
     * Prepare the cart response
     *
     * @param Cart $cart The cart to include in the response
     * @param array $removedItems Items removed from cart due to availability
     * @param array|null $zone Delivery zone information
     * @param int|null $addressId Resolved address id (null if no address)
     * @return array Response with cart data
     */
    private function prepareCartResponse(Cart $cart, array $removedItems, ?array $zone, ?int $addressId = null): array
    {
        return [
            'success' => true,
            'message' => count($removedItems) > 0 ? __('messages.cart_updated_based_on_location') : __('messages.cart_location_verified'),
            'data' => [
                'cart' => $cart,
                'removed_items' => $removedItems,
                'removed_count' => count($removedItems),
                'delivery_zone' => $zone,
                'address_id' => $addressId,
            ],
        ];
    }

    /**
     * Resolve per-market additional charges (flat, base currency).
     *
     * Reads `platform_fee` / `cod_fee` off the request's resolved market,
     * falling back to the default market. The platform fee always applies; the
     * COD fee only when the selected payment method is Cash on Delivery — so
     * for the cart preview the client must pass the chosen payment type through.
     *
     * @return array{platform_fee: float, cod_fee: float}
     */
    private function resolveAdditionalCharges(?string $paymentType): array
    {
        $market = request()?->attributes->get('market');
        if (!$market instanceof Market) {
            $market = Market::query()->where('is_default', true)->first();
        }

        if (!$market) {
            return ['platform_fee' => 0.0, 'cod_fee' => 0.0];
        }

        $platformFee = (float)($market->platform_fee ?? 0);

        $isCod = $paymentType !== null
            && strtolower($paymentType) === PaymentTypeEnum::COD();
        $codFee = $isCod ? (float)($market->cod_fee ?? 0) : 0.0;

        return ['platform_fee' => $platformFee, 'cod_fee' => $codFee];
    }

    /**
     * Per-market Cash-on-Delivery cap (flat, base currency).
     *
     * Reads `max_cod_amount` off the request's resolved market, falling back to
     * the default market. `0` (or missing) means no cap.
     */
    private function resolveMaxCodAmount(): float
    {
        $market = request()?->attributes->get('market');
        if (!$market instanceof Market) {
            $market = Market::query()->where('is_default', true)->first();
        }

        return $market ? (float)($market->max_cod_amount ?? 0) : 0.0;
    }

    /**
     * Roll the per-line fulfillment ETAs up into one cart-level estimate.
     *
     * Takes the earliest of the line minimums and the latest of the line
     * maximums, so the customer sees the full "arrives in X–Y" window for the
     * whole basket. Unit is taken from the first line that has one (we assume a
     * consistent unit across the cart — days in practice).
     *
     * @param \Illuminate\Support\Collection $decisions
     * @return array{min: int, max: int, unit: string}|null  Null when no line has an ETA.
     */
    private function aggregateDeliveryEta($decisions): ?array
    {
        $mins = [];
        $maxs = [];
        $unit = null;

        foreach ($decisions as $decision) {
            $min = $decision['eta_min'] ?? null;
            $max = $decision['eta_max'] ?? null;

            if ($min !== null) {
                $mins[] = (int) $min;
            }
            if ($max !== null) {
                $maxs[] = (int) $max;
            }
            if ($unit === null && !empty($decision['eta_unit'])) {
                $unit = (string) $decision['eta_unit'];
            }
        }

        if (empty($mins) && empty($maxs)) {
            return null;
        }

        $min = !empty($mins) ? min($mins) : min($maxs);
        $max = !empty($maxs) ? max($maxs) : max($mins);

        return [
            'min'  => $min,
            'max'  => $max,
            'unit' => $unit ?? 'days',
        ];
    }

    /**
     * Calculate payment summary for a cart under standard e-commerce model
     *
     * @param Cart $cart The cart to calculate payment summary for
     * @param Address|null $address User's shipping address
     * @param bool $useWallet Whether to use wallet balance for payment
     * @param string|null $promoCode Promo code to apply discount
     * @param string|null $paymentType Selected payment method (drives the COD fee)
     * @return array Payment summary details
     */
    public function getPaymentSummary(Cart $cart, ?Address $address = null, bool $useWallet = false, ?string $promoCode = null, ?string $paymentType = null): array
    {
        try {
            $totalsResult = $this->calculateCartTotals($cart);
            $itemsTotal = (float)$totalsResult['items_total'];
            $totalSaving = (float)($totalsResult['total_saving'] ?? 0);
            $totalStores = count($totalsResult['store_ids']);

            $fees = $this->resolveAdditionalCharges($paymentType);
            $platformFee = $fees['platform_fee'];
            $codFee = $fees['cod_fee'];
            $additionalChargesTotal = $platformFee + $codFee;

            $isCodAllowed = $cart->items->every(
                fn($item) => (string)($item->product?->allow_cod ?? '1') === '1'
            );

            $decisions = $this->resolveFulfillmentDecisions(
                $cart,
                $address,
                $itemsTotal,
                strtolower((string) $paymentType) === (string) PaymentTypeEnum::COD(),
            );
            $shipping = $this->buildSellerShippingCosts($cart, $decisions, $address);
            $totalDeliveryCharges = $shipping['total'];

            $tax = $this->calculateTaxBreakdown($cart);
            $pending = $this->resolvePendingCharges((int)$cart->user_id);

            $payableAmount = $itemsTotal + $totalDeliveryCharges + $pending['total'] + $additionalChargesTotal;

            $promo = $this->resolvePromoForSummary($promoCode, $cart, $itemsTotal, $totalDeliveryCharges, $payableAmount);
            $payableAmount = $promo['payable_amount'];

            $walletAmountUsed = 0.0;
            $orderTotal = $payableAmount;
            $wallet = $cart->user->wallet()->first();
            $walletBalance = !empty($wallet) ? (float)$wallet->balance : 0.0;
            if ($useWallet && $wallet) {
                $walletAmountUsed = min($walletBalance, $payableAmount);
                $payableAmount = $payableAmount - $walletAmountUsed;
            }

            $codAvailable = $this->resolveCodAvailability($isCodAllowed, $orderTotal, $address, $decisions);

            return [
                'items_total' => (float)$itemsTotal,
                'total_saving' => (float)$totalSaving,
                'cod_available' => $codAvailable,
                'products_allow_cod' => (bool)$isCodAllowed,
                'delivery_eta' => $this->aggregateDeliveryEta($decisions),
                'platform_fee' => (float)$platformFee,
                'cod_fee' => (float)$codFee,
                'additional_charges_total' => (float)$additionalChargesTotal,
                'per_store_drop_off_fee' => 0.0,
                'is_rush_delivery' => false,
                'is_rush_delivery_available' => false,
                'delivery_charges' => (float)$totalDeliveryCharges,
                'regular_delivery_charge' => (float)$totalDeliveryCharges,
                'rush_delivery_charge' => 0.0,
                'delivery_distance_charges' => 0.0,
                'delivery_distance_km' => 0.0,
                'total_stores' => (float)$totalStores,
                'total_delivery_charges' => (float)$totalDeliveryCharges,
                'estimated_delivery_time' => 0,
                'promo_code' => $promoCode,
                'promo_discount' => (float)$promo['discount'],
                'promo_applied' => $promo['applied'],
                'promo_error' => $promo['error'],
                'use_wallet' => $useWallet,
                'wallet_balance' => (float)$walletBalance,
                'wallet_amount_used' => (float)$walletAmountUsed,
                'payable_amount' => (float)$payableAmount,
                'order_total' => (float)$orderTotal,
                'seller_shipping_costs' => $shipping['costs'],
                'pending_charges' => $pending['charges'],
                'pending_charges_total' => (float)$pending['total'],
                'tax_breakdown' => $tax['breakdown'],
                'tax_total' => $tax['total'],
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating payment summary: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return $this->createDefaultPaymentSummary($cart, $useWallet);
        }
    }

    private function resolveFulfillmentDecisions(Cart $cart, ?Address $address, float $itemsTotal, bool $cod = false): Collection
    {
        if (!$address) {
            return $cart->items->map(fn($item) => [
                'cart_item_id' => $item->id,
                'location' => null,
                'zone' => null,
                'rate' => 0.0,
                'eta_min' => null,
                'eta_max' => null,
                'eta_unit' => null,
                'unfulfillable' => null,
            ])->values();
        }

        $cartWeight = $cart->items->sum(
            fn($item) => (float)($item->variant->weight ?? 0.0) * (int)$item->quantity
        );

        $destination = [
            'country_iso2' => strtoupper(substr($address->country_code ?? '', 0, 2)),
            'state_code' => $address->state,
            'city_name' => $address->city,
            'pin_code' => $address->zipcode,
            'cart_total' => $itemsTotal,
            'cart_weight' => $cartWeight,
            'cod' => $cod,
        ];

        return app(FulfillmentRouterService::class)->routeCart($cart->items, $destination);
    }

    private function buildSellerShippingCosts(Cart $cart, Collection $decisions, ?Address $address): array
    {
        $decisionsByItem = $decisions->keyBy('cart_item_id');
        $router = app(FulfillmentRouterService::class);

        $sellerShippingCosts = [];
        $totalDeliveryCharges = 0.0;

        foreach ($cart->items->groupBy('store_id') as $storeId => $storeItems) {
            $rateByItemId = [];
            $storeShippingCost = 0.0;

            foreach ($router->groupShipments($storeItems, $decisions) as $group) {
                $storeShippingCost += (float)$group['rate'];
                $firstInGroup = $group['items']->first();
                if ($firstInGroup) {
                    $rateByItemId[(int)$firstInGroup->id] = (float)$group['rate'];
                }
            }

            $sellerShippingItems = [];
            $storeFulfillable = true;
            foreach ($storeItems as $item) {
                $itemEntry = $this->buildSellerShippingItem($item, $decisionsByItem->get($item->id), $rateByItemId, $address);
                $storeFulfillable = $storeFulfillable && $itemEntry['is_fulfillable'];
                $sellerShippingItems[] = $itemEntry;
            }

            $totalDeliveryCharges += $storeShippingCost;
            $store = $storeItems->first()->store;
            $storeDecisions = $storeItems
                ->map(fn($it) => $decisionsByItem->get($it->id))
                ->filter();
            $sellerShippingCosts[] = [
                'store_id' => (int)$storeId,
                'store_name' => $store ? $store->name : '',
                'shipping_cost' => $storeShippingCost,
                'is_fulfillable' => $storeFulfillable,
                'delivery_eta' => $this->aggregateDeliveryEta($storeDecisions),
                'items' => $sellerShippingItems,
            ];
        }

        return ['costs' => $sellerShippingCosts, 'total' => $totalDeliveryCharges];
    }

    private function buildSellerShippingItem(CartItem $item, ?array $decision, array $rateByItemId, ?Address $address): array
    {
        $etaMin = $decision['eta_min'] ?? null;
        $etaMax = $decision['eta_max'] ?? null;
        $etaUnit = $decision['eta_unit'] ?? null;

        $item->eta_min = $etaMin !== null ? (int)$etaMin : null;
        $item->eta_max = $etaMax !== null ? (int)$etaMax : null;
        $item->eta_unit = $etaUnit;

        return [
            'cart_item_id' => (int)$item->id,
            'product_name' => $item->product->title,
            'is_fulfillable' => $address ? ($decision && $decision['unfulfillable'] === null) : true,
            'unfulfillable_reason' => ($decision && $decision['unfulfillable'] !== null)
                ? __("labels." . $decision['unfulfillable'])
                : null,
            'shipping_rate' => (float)($rateByItemId[(int)$item->id] ?? 0.0),
            'location_id' => $decision && $decision['location'] ? (int)$decision['location']->id : null,
            'location_name' => $decision && $decision['location'] ? $decision['location']->name : null,
            'shipping_zone_id' => $decision && $decision['zone'] ? (int)$decision['zone']->id : null,
            'eta_min' => $etaMin !== null ? (int)$etaMin : null,
            'eta_max' => $etaMax !== null ? (int)$etaMax : null,
            'eta_unit' => $etaUnit,
        ];
    }

    private function calculateTaxBreakdown(Cart $cart): array
    {
        $market = request()?->attributes->get('market');
        $market = $market instanceof Market ? $market : null;

        $breakdown = [];
        $taxTotal = 0.0;

        foreach ($cart->items as $item) {
            $rates = $this->resolveItemTaxRates($item, $market);
            $totalTaxPercent = (float)$rates->sum('rate');
            if ($totalTaxPercent <= 0) {
                continue;
            }

            $effectivePrice = (float)($item->variant->special_price_exclude_tax > 0 ? $item->variant->special_price_exclude_tax : $item->variant->price_exclude_tax);
            $linePrice = ($effectivePrice + $this->sumCartItemAddonPrices($item)) * (int)$item->quantity;
            $isInclusive = (string)($item->product->is_inclusive_tax ?? '1') === '1';

            foreach ($rates as $taxRate) {
                $ratePercent = (float)$taxRate->rate;
                $taxAmount = $isInclusive
                    ? $linePrice * ($ratePercent / 100) / (1 + $totalTaxPercent / 100)
                    : $linePrice * ($ratePercent / 100);

                $taxTotal += $taxAmount;

                $rateId = (int)$taxRate->id;
                $breakdown[$rateId] ??= [
                    'tax_rate_id' => $rateId,
                    'title' => $taxRate->title,
                    'rate' => $ratePercent,
                    'amount' => 0.0,
                ];
                $breakdown[$rateId]['amount'] += $taxAmount;
            }
        }

        $breakdownValues = array_map(
            fn($entry) => array_merge($entry, ['amount' => $entry['amount']]),
            array_values($breakdown)
        );

        return ['breakdown' => $breakdownValues, 'total' => $taxTotal];
    }

    private function resolveItemTaxRates(CartItem $item, ?Market $market): Collection
    {
        $product = $item->product;
        if (!$product) {
            return collect();
        }

        $storeVariant = $item->variant->storeProductVariants->where('store_id', $item->store_id)->first();
        if (!$storeVariant) {
            return collect();
        }

        $taxClasses = $product->relationLoaded('taxClasses')
            ? $product->taxClasses
            : $product->taxClasses()->get();

        if ($market) {
            $scoped = $taxClasses->where('market_id', $market->id);

            if ($scoped->isEmpty() && $taxClasses->isNotEmpty()) {
                Log::warning('No tax class matched the market; falling back to the product default', [
                    'product_id' => $product->id,
                    'market_id' => $market->id,
                ]);
            } else {
                $taxClasses = $scoped;
            }
        }

        return $taxClasses->flatMap(fn($taxClass) => $taxClass->taxRates)->values();
    }

    private function resolvePendingCharges(int $userId): array
    {
        $pendingCharges = DB::table('customer_pending_charges')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        $charges = $pendingCharges->map(fn($charge) => [
            'id' => (int)$charge->id,
            'amount' => (float)$charge->amount,
            'reason' => $charge->reason,
            'reason_note' => $charge->reason_note,
            'seller_order_id' => $charge->seller_order_id ? (int)$charge->seller_order_id : null,
        ])->all();

        return ['charges' => $charges, 'total' => (float)$pendingCharges->sum('amount')];
    }

    private function resolvePromoForSummary(?string $promoCode, Cart $cart, float $itemsTotal, float $totalDeliveryCharges, float $payableAmount): array
    {
        $result = [
            'discount' => 0.0,
            'applied' => null,
            'error' => null,
            'payable_amount' => $payableAmount,
        ];

        if (empty($promoCode)) {
            return $result;
        }

        $promoResult = $this->validateAndApplyPromoCode(
            promoCode: $promoCode,
            user: $cart->user,
            cartTotal: $itemsTotal,
            deliveryCharge: $totalDeliveryCharges
        );

        if (!$promoResult['success']) {
            $result['error'] = $promoResult['message'];
            return $result;
        }

        $result['discount'] = (float)$promoResult['discount'];
        $result['applied'] = $promoResult['promo'];
        if ($result['applied']['promo_mode'] === PromoModeEnum::INSTANT()) {
            $result['payable_amount'] = max(0.0, $payableAmount - $result['discount']);
        }

        return $result;
    }

    private function resolveCodAvailability(bool $isCodAllowed, float $orderTotal, ?Address $address, Collection $decisions): bool
    {
        if (!$isCodAllowed || $address === null || $decisions->isEmpty()) {
            return false;
        }

        $maxCodAmount = $this->resolveMaxCodAmount();
        if ($maxCodAmount > 0 && $orderTotal > $maxCodAmount) {
            return false;
        }

        $codCapable = array_map('strval', FulfillmentMethodEnum::codCapable());

        return !$decisions->contains(function ($d) use ($codCapable) {
            $method = (string)(($d['zone']?->method) ?? FulfillmentMethodEnum::MANUAL());
            return !in_array($method, $codCapable, true);
        });
    }


    /**
     * Create a default payment summary with zeros
     *
     * @param Cart $cart The cart to create default summary for
     * @param bool $useWallet Whether to use wallet balance for payment
     * @return array Default payment summary
     */
    private function createDefaultPaymentSummary(Cart $cart, bool $useWallet = false): array
    {
        $totalsResult = $this->calculateCartTotals($cart);
        $itemsTotal = $totalsResult['items_total'] ?? 0;
        $totalSaving = $totalsResult['total_saving'] ?? 0;
        $productsAllowCod = $cart->items->every(
            fn($item) => (string)($item->product?->allow_cod ?? '1') === '1'
        );
        $walletAmountUsed = 0;
        $remainingPayable = $itemsTotal;
        $orderTotal = $remainingPayable;
        $wallet = $cart->user->wallet()->first();
        $walletBalance = !empty($wallet) ? $wallet->balance : 0;
        if ($useWallet && $cart->user) {
            if ($wallet) {
                $walletAmountUsed = min($walletBalance, $itemsTotal);
                $remainingPayable = $itemsTotal - $walletAmountUsed;
            }
        }

        return [
            'items_total' => (float)$itemsTotal,
            'total_saving' => (float)$totalSaving,
            'cod_available' => false,
            'products_allow_cod' => (bool)$productsAllowCod,
            'delivery_eta' => null,
            'platform_fee' => 0.0,
            'cod_fee' => 0.0,
            'additional_charges_total' => 0.0,
            'per_store_drop_off_fee' => 0.0,
            'is_rush_delivery' => false,
            'is_rush_delivery_available' => false,
            'delivery_charges' => 0.0,
            'regular_delivery_charge' => 0.0,
            'rush_delivery_charge' => 0.0,
            'delivery_distance_charges' => 0.0,
            'delivery_distance_km' => 0.0,
            'total_stores' => 0.0,
            'total_delivery_charges' => 0.0,
            'estimated_delivery_time' => 0,
            'use_wallet' => $useWallet,
            'wallet_balance' => (float)$walletBalance,
            'wallet_amount_used' => (float)$walletAmountUsed,
            'payable_amount' => (float)$remainingPayable,
            'order_total' => (float)$orderTotal,
            'seller_shipping_costs' => [],
            'pending_charges' => [],
            'pending_charges_total' => 0.0,
            'tax_breakdown' => [],
            'tax_total' => 0.0,
        ];
    }

    /**
     * Remove item from the cart
     */
    public function removeFromCart(User $user, int $cartItemId): array
    {
        try {
            DB::beginTransaction();

            $cartItem = CartItem::whereHas('cart', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->find($cartItemId);

            if (!$cartItem) {
                return [
                    'success' => false,
                    'message' => __('messages.cart_item_not_found'),
                    'data' => [],
                ];
            }

            $cartItem->load(['product', 'variant', 'store']);
            $cart = $cartItem->cart;

            $cartItem->delete();

            event(new ItemRemovedFromCart($cart, $cartItem, $user));

            $cart = $this->getUserCart($user);

            DB::commit();

            return [
                'success' => true,
                'message' => __('messages.item_removed_from_cart_successfully'),
                'data' => $cart,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    public function buyNow(User $user, array $data): array
    {
        $result = null;
        try {
            DB::transaction(function () use ($user, $data, &$result) {
                CartItem::whereHas('cart', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->where('save_for_later', '0')->update(['save_for_later' => '1']);

                $payload = $data;
                $payload['replace_quantity'] = true;
                $result = $this->addToCart($user, $payload);

                if (!($result['success'] ?? false)) {
                    throw new \RuntimeException('buy_now_add_failed');
                }
            });

            return $result;
        } catch (\RuntimeException $e) {
            return $result ?? [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Save cart item for later
     */
    public function addToSaveForLater(User $user, int $cartItemId): array
    {
        try {
            DB::beginTransaction();

            $cartItem = CartItem::whereHas('cart', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->find($cartItemId);

            if (!$cartItem) {
                return [
                    'success' => false,
                    'message' => __('messages.cart_item_not_found'),
                    'data' => [],
                ];
            }

            $cartItem->load(['product', 'variant', 'store']);
            $cart = $cartItem->cart;

            $cartItem->update(['save_for_later' => true]);

            event(new ItemRemovedFromCart($cart, $cartItem, $user));

            $cart = $this->getSaveForLaterCart($user);

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.item_saved_for_later_successfully'),
                'data' => $cart,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Update cart item quantity (and, optionally, its addon selections).
     *
     * `$addons` semantics:
     *  - `null`  → leave existing addons untouched
     *  - `[]`    → clear all addons on the line (signature goes back to NULL)
     *  - `[...]` → replace the entire set; validates against variant attachments
     *              and recomputes the line's `addon_signature`.
     *
     * @param array<int,array{addon_group_id:int|string,addon_item_id:int|string}>|null $addons
     */
    public function updateCartItemQuantity(User $user, int $cartItemId, int $quantity, ?array $addons = null): array
    {
        try {
            return DB::transaction(function () use ($addons, $cartItemId, $quantity, $user) {
                $cartItem = CartItem::whereHas('cart', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->find($cartItemId);

                if (!$cartItem) {
                    return [
                        'success' => false,
                        'message' => __('messages.cart_item_not_found'),
                        'data' => [],
                    ];
                }

                $storeProductVariant = StoreProductVariant::where('store_id', $cartItem->store_id)
                    ->where('product_variant_id', $cartItem->product_variant_id)
                    ->first();

                $availableStock = $storeProductVariant ? $storeProductVariant->stock : 0;

                if (!$storeProductVariant || ($availableStock !== null && $quantity > $availableStock)) {
                    return [
                        'success' => false,
                        'message' => __('messages.insufficient_stock_available'),
                        'data' => ['available_stock' => $availableStock ?? 0],
                    ];
                }

                $rules = $this->extractQuantityRules($storeProductVariant);
                if ($error = $this->validateQuantityRules($quantity, $rules)) {
                    return $error;
                }

                if ($addons !== null) {
                    $addonResolution = $this->resolveAddonSelections(
                        storeId: (int)$cartItem->store_id,
                        productVariantId: (int)$cartItem->product_variant_id,
                        addons: $addons,
                    );
                    if (!$addonResolution['success']) {
                        return $addonResolution['error'];
                    }
                    $resolvedAddons = $addonResolution['resolved'];

                    if ($error = $this->checkAddonsStock($resolvedAddons, $quantity)) {
                        return $error;
                    }

                    $newSignature = CartItem::buildAddonSignature(
                        array_column($resolvedAddons, 'addon_item_id')
                    );

                    $this->persistCartItemAddons($cartItem, $resolvedAddons);

                    $cartItem->update([
                        'quantity' => $quantity,
                        'addon_signature' => $newSignature,
                    ]);
                } else {
                    if ($error = $this->checkExistingAddonsStock($cartItem, $quantity)) {
                        return $error;
                    }
                    $cartItem->update(['quantity' => $quantity]);
                }

                $cart = $this->getUserCart($user);

                return [
                    'success' => true,
                    'message' => __('messages.cart_item_quantity_updated_successfully'),
                    'data' => $cart,
                ];
            });

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Clear entire cart
     */
    public function clearCart(User $user): array
    {
        try {
            return DB::transaction(function () use ($user) {
                $cart = $this->getUserCart($user);

                if (!$cart) {
                    return [
                        'success' => true,
                        'message' => __('messages.cart_is_empty'),
                        'data' => [],
                    ];
                }

                $cart->items()->where('save_for_later', '0')->delete();

                return [
                    'success' => true,
                    'message' => __('messages.cart_cleared_successfully'),
                    'data' => [],
                ];
            });

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Check if rush delivery is available in the given zone
     *
     * @param array $zone Delivery zone information
     * @return bool True if rush delivery is available, false otherwise
     */
    private function isRushDeliveryAvailable(array $zone): bool
    {
        return isset($zone['rush_delivery_enabled']) &&
            $zone['rush_delivery_enabled'] &&
            isset($zone['rush_delivery_charges']) &&
            isset($zone['rush_delivery_time_per_km']);
    }

    /**
     * Public method to validate promo code
     *
     * @param string $promoCode The promo code to validate
     * @param User $user The user applying the promo code
     * @param float $cartTotal The order amount before discount
     * @return array Result with success status, discount amount, and promo details
     */
    public function toBaseAmount(float $amount, ?Market $market): float
    {
        $baseCode = PricePresenter::baseCode();

        if (!$market || $baseCode === '' || strtoupper($market->currency_code) === strtoupper($baseCode)) {
            return $amount;
        }

        $converted = app(FxRateService::class)->convertCurrency(
            $amount,
            $market->currency_code,
            $baseCode,
        );

        return $converted ?? $amount;
    }

    public function validatePromoCode(string $promoCode, User $user, float $cartTotal, float $deliveryCharge): array
    {
        return $this->validateAndApplyPromoCode($promoCode, $user, $cartTotal, $deliveryCharge);
    }

    /**
     * Validate and apply promo code discount
     *
     * @param string $promoCode The promo code to validate
     * @param User $user The user applying the promo code
     * @param float $cartTotal The order amount before discount
     * @return array Result with success status, discount amount, and promo details
     */
    private function validateAndApplyPromoCode(string $promoCode, User $user, float $cartTotal, float $deliveryCharge): array
    {
        try {
            $promo = Promo::where('code', $promoCode)->first();

            if ($error = $this->validatePromoEligibility($promo, $user, $cartTotal)) {
                return $error;
            }

            $discount = $this->calculateDiscount($promo, $cartTotal, $deliveryCharge);

            return $this->promoResponse(true, 'promo_code_applied_successfully', $discount, $promo);

        } catch (\Exception $e) {
            Log::error('Error validating promo code: ' . $e->getMessage());
            return $this->promoResponse(false, 'promo_code_validation_error');
        }
    }

    private function validatePromoEligibility(?Promo $promo, User $user, float $cartTotal): ?array
    {
        $now = now();

        if (!$promo || ($promo->start_date && $now->lt($promo->start_date))) {
            return $this->promoResponse(false, 'invalid_promo_code');
        }

        if ($promo->deleted_at || ($promo->end_date && $now->gt($promo->end_date))) {
            return $this->promoResponse(false, 'promo_code_expired');
        }

        if ($promo->min_order_total && $cartTotal < (float) $promo->min_order_total) {
            $formattedMin = PricePresenter::forCurrent((float) $promo->min_order_total)['formatted'] ?? (string) $promo->min_order_total;
            return $this->promoResponse(false, 'minimum_order_amount_not_met', 0, null, ['amount' => $formattedMin]);
        }

        if ($promo->max_total_usage && $promo->usage_count >= $promo->max_total_usage) {
            return $this->promoResponse(false, 'promo_code_usage_limit_exceeded');
        }

        if ($promo->max_usage_per_user && $this->getUserUsageCount($promo, $user) >= $promo->max_usage_per_user) {
            return $this->promoResponse(false, 'promo_code_user_limit_exceeded');
        }

        return null;
    }

    private function getUserUsageCount(Promo $promo, User $user): int
    {
        return OrderPromoLine::where('promo_id', $promo->id)
            ->whereHas('order', fn($q) => $q->where('user_id', $user->id))
            ->count();
    }

    private function calculateDiscount(Promo $promo, float $cartTotal, float $deliveryCharge): float
    {
        $discount = match ($promo->discount_type) {
            PromoDiscountTypeEnum::PERCENTAGE() => min(
                ($cartTotal * $promo->discount_amount) / 100,
                $promo->max_discount_value ?? PHP_FLOAT_MAX
            ),
            PromoDiscountTypeEnum::FIXED() => $promo->discount_amount,
            PromoDiscountTypeEnum::FREE_SHIPPING() => min(
                $deliveryCharge,
                $promo->max_discount_value ?? PHP_FLOAT_MAX
            ),
            default => 0,
        };

        if ((string) $promo->discount_type === (string) PromoDiscountTypeEnum::FREE_SHIPPING()) {
            return $discount;
        }

        return min($discount, $cartTotal);
    }

    private function promoResponse(bool $success, string $messageKey, float $discount = 0, ?Promo $promo = null, array $params = []): array
    {
        return [
            'success' => $success,
            'message' => __("messages.$messageKey", $params),
            'discount' => $discount,
            'promo' => $promo,
        ];
    }

    /**
     * Reorder: Add all items from a past order back into the user's cart.
     *
     * Quantity logic: Always add using the product's quantity_step_size (default 1). Any
     * provided quantities or previous order quantities are ignored as per requirement.
     *
     * @param User $user The authenticated user requesting reorder
     * @param int $orderId The ID of the order to reorder
     * @param array|null $quantities Ignored. Kept for backward compatibility.
     * @return array Standard service response with per-item results
     */
    public function reorderFromOrder(User $user, int $orderId): array
    {
        try {
            $order = Order::with(['items.product', 'items.addons'])
                ->where('id', $orderId)
                ->where('user_id', $user->id)
                ->first();
            if (!$order) {
                return [
                    'success' => false,
                    'message' => __('labels.order_not_found_or_not_yours') ?? __('messages.order_not_found'),
                    'data' => [],
                ];
            }

            if ($order->items->isEmpty()) {
                return [
                    'success' => false,
                    'message' => __('messages.no_items_found') ?? 'No items found in the order.',
                    'data' => [],
                ];
            }

            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($order->items as $item) {
                $stepSize = max(1, (int)($item->product->quantity_step_size ?? 1));
                $orderedQty = max(1, (int) $item->quantity);
                $desiredQty = (int) (ceil($orderedQty / $stepSize) * $stepSize);

                $addons = $item->addons
                    ->map(fn ($addon) => [
                        'addon_group_id' => (int) $addon->addon_group_id,
                        'addon_item_id' => (int) $addon->addon_item_id,
                    ])
                    ->values()
                    ->all();

                $addRes = $this->addToCart($user, [
                    'store_id' => $item->store_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $desiredQty,
                    'replace_quantity' => true,
                    'addons' => $addons,
                ]);

                $results[] = [
                    'order_item_id' => $item->id,
                    'store_id' => $item->store_id,
                    'product_variant_id' => $item->product_variant_id,
                    'requested_quantity' => $desiredQty,
                    'success' => $addRes['success'] ?? false,
                    'message' => $addRes['message'] ?? null,
                    'data' => $addRes['data'] ?? [],
                ];

                if (!empty($addRes['success'])) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            }

            $overallSuccess = $successCount > 0 && $failureCount === 0;
            $partial = $successCount > 0 && $failureCount > 0;

            $message = __('labels.success');
            if ($partial) {
                $message = __('labels.partial_success') ?? 'Some items could not be added to the cart.';
            } elseif ($successCount === 0) {
                $message = __('labels.failed_to_add_items_to_cart') ?? 'Failed to add items to cart.';
            }

            return [
                'success' => $overallSuccess || $partial,
                'message' => $message,
                'data' => [
                    'added' => $successCount,
                    'failed' => $failureCount,
                    'items' => $results,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Reorder failed', [
                'order_id' => $orderId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('messages.something_went_wrong') ?? 'Something went wrong',
                'data' => [],
            ];
        }
    }
}
