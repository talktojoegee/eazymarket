<?php

namespace App\Services;

use App\Services\MarketService;
use App\Enums\DateRangeFilterEnum;
use App\Enums\Order\CustomerItemStatusEnum;
use App\Enums\Order\CancelReasonEnum;
use App\Enums\Order\FulfillmentStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\RefundMethodEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\PromoModeEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Models\ShipmentItem;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderStatusUpdated;
use App\Http\Resources\User\OrderItemReturnResource;
use App\Http\Resources\User\OrderResource;
use App\Http\Resources\User\ReviewResource;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Currency;
use App\Models\Market;
use App\Models\Order;
use App\Models\CartItemAddon;
use App\Models\OrderAuditLog;
use App\Models\OrderItem;
use App\Models\OrderItemComboComponent;
use App\Models\OrderItemAddon;
use App\Models\OrderItemReturn;
use App\Models\OrderPaymentTransaction;
use App\Models\OrderPromoLine;
use App\Services\AddonStockService;
use App\Models\OrderRefund;
use App\Models\Promo;
use App\Models\Review;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Services\Fx\FxRateService;
use App\Services\OrderNumberService;
use App\Support\PricePresenter;
use App\Types\Api\ApiResponseType;
use App\Enums\SpatieMediaCollectionName;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    protected StockService $stockService;
    protected SettingService $settingService;
    protected PaymentService $paymentService;
    protected SellerStatementService $sellerStatementService;
    protected WalletService $walletService;

    public function __construct(
        StockService           $stockService,
        SettingService         $settingService,
        PaymentService         $paymentService,
        SellerStatementService $sellerStatementService,
        WalletService          $walletService
    )
    {
        $this->settingService = $settingService;
        $this->stockService = $stockService;
        $this->paymentService = $paymentService;
        $this->sellerStatementService = $sellerStatementService;
        $this->walletService = $walletService;
    }

    /**
     * Create a new order from the cart
     *
     * @param User $user The user placing the order
     * @param array $data Order data including payment and address information
     * @return array Result containing success status, message, and order data
     */
    public function createOrder(User $user, array $data): array
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;

        if (empty($idempotencyKey)) {
            $cart = CartService::getUserCart($user);

            if ($cart) {
                $idempotencyKey = 'auto_' . sha1(implode('|', [
                    $user->id,
                    $cart->id,
                    (string) $cart->updated_at,
                    (string) ($data['payment_method'] ?? ''),
                ]));
                $data['idempotency_key'] = $idempotencyKey;
            }
        }

        if (!empty($idempotencyKey)) {
            $existingOrder = Order::where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existingOrder) {
                return [
                    'success' => true,
                    'message' => __('messages.order_created_successfully'),
                    'data' => $existingOrder
                ];
            }
        }

        try {
            DB::beginTransaction();

            $this->supersedePriorPendingOrders($user);

            $settingService = app(SettingService::class);
            $settings = $settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
            $cartValidation = $this->validateCartAndSettings($user, $settings);
            if (!$cartValidation['success']) {
                DB::rollBack();
                return $cartValidation;
            }

            $paymentValidation = $this->validatePaymentMethod(data: $data);
            if (!$paymentValidation['success']) {
                DB::rollBack();
                return $paymentValidation;
            }

            $addressValidation = $this->validateAddress(user: $user, data: $data);
            if (!$addressValidation['success']) {
                DB::rollBack();
                return $addressValidation;
            }
            $data = $addressValidation['data'];

            $stockAndDeliveryValidation = $this->validateStockAndDeliveryAvailability(
                cart: $cartValidation['cart'],
                address: $data['address'],
                cod: strtolower((string) ($data['payment_type'] ?? '')) === (string) PaymentTypeEnum::COD(),
            );
            if (!$stockAndDeliveryValidation['success']) {
                DB::rollBack();
                return $stockAndDeliveryValidation;
            }
            $fulfillmentDecisions = $stockAndDeliveryValidation['decisions'];

            $codValidation = $this->validateCodAgainstFulfillment($data, $fulfillmentDecisions);
            if (!$codValidation['success']) {
                DB::rollBack();
                return $codValidation;
            }

            $codProductValidation = $this->validateCodAgainstProducts($data, $cartValidation['cart']);
            if (!$codProductValidation['success']) {
                DB::rollBack();
                return $codProductValidation;
            }

            $codCapValidation = $this->validateCodAgainstCap($data, $cartValidation['cart']);
            if (!$codCapValidation['success']) {
                DB::rollBack();
                return $codCapValidation;
            }

            $walletCoverageValidation = $this->validateWalletCoversTotal($data, $cartValidation['cart']);
            if (!$walletCoverageValidation['success']) {
                DB::rollBack();
                return $walletCoverageValidation;
            }

            $data['minimumCartAmount'] = $settings->value['minimumCartAmount'] ?? 1;
            $order = $this->processOrderCreation(user: $user, cart: $cartValidation['cart'], data: $data);

            $finalizationResult = $this->finalizeOrderCreation(
                order: $order,
                cart: $cartValidation['cart'],
                user: $user,
                data: $data,
                fulfillmentDecisions: $fulfillmentDecisions
            );
            if (!$finalizationResult['success']) {
                DB::rollBack();
                return $finalizationResult;
            }
            DB::commit();

            return [
                'success' => true,
                'message' => __('messages.order_created_successfully'),
                'data' => $finalizationResult['order']
            ];

        } catch (UniqueConstraintViolationException $e) {
            DB::rollBack();
            if (!empty($idempotencyKey)) {
                $existingOrder = Order::where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existingOrder) {
                    return [
                        'success' => true,
                        'message' => __('messages.order_created_successfully'),
                        'data' => $existingOrder
                    ];
                }
            }
            Log::error('Error creating order: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => []
            ];
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => __('labels.model_not_found'),
                'data' => []
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating order: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => []
            ];
        }
    }

    /**
     * Validate cart and system settings
     *
     * @param User $user
     * @return array
     */
    private function validateCartAndSettings(User $user, $settings): array
    {

        $cart = CartService::getUserCart($user);
        $storeCount = CartService::cartStoreCount($user);

        if ($settings->value['checkoutType'] === "single_store" && $storeCount > 1) {
            return [
                'success' => false,
                'message' => __('labels.checkout_type_single_store_error'),
                'data' => []
            ];
        }

        if (!$cart || $cart->items->isEmpty()) {
            return [
                'success' => false,
                'message' => __('messages.cart_is_empty'),
                'data' => []
            ];
        }

        $itemMaxAllowValidate = CartService::validateCartMaxItems($cart, isAdding: false);
        if (!$itemMaxAllowValidate) {
            return [
                'success' => false,
                'message' => __('labels.cart_max_items_exceeded'),
                'data' => []
            ];
        }

        $offlineStores = $cart->items->filter(function ($item) {
            $store = $item->store;
            if (!$store) {
                return false;
            }
            $vis = $store->visibility_status;
            $value = $vis instanceof \BackedEnum ? $vis->value : (string)($vis ?? '');
            return $value !== '' && $value !== StoreVisibilityStatusEnum::VISIBLE();
        })->map(function ($item) {
            return $item->store?->name;
        })->unique()->values()->all();

        if (!empty($offlineStores)) {
            return [
                'success' => false,
                'message' => __('messages.store_offline_cannot_place_order', ['stores' => implode(', ', $offlineStores)]),
                'data' => ['stores' => $offlineStores]
            ];
        }

        return [
            'success' => true,
            'cart' => $cart
        ];
    }

    /**
     * Validate payment method
     *
     * @param array $data
     * @return array
     */
    private function validatePaymentMethod(array $data): array
    {
        $transformedSetting = $this->settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());
        $paymentSettings = $transformedSetting->toArray(request())['value'] ?? [];
        $paymentMethodEnabled = $paymentSettings[$data['payment_type']] ?? false;

        if (($paymentSettings['wallet'] ?? false) === false && $this->usesWallet($data)) {
            return [
                'success' => false,
                'message' => __('labels.wallet_not_enabled'),
                'data' => []
            ];
        }

        if ($paymentMethodEnabled === false) {
            return [
                'success' => false,
                'message' => __('labels.payment_method_not_enabled', ['payment_method' => ucfirst($data['payment_type'])]),
                'data' => []
            ];
        }

        $paymentStatus = $this->paymentService->verifyOnlinePayment($data);
        if ($paymentStatus['success'] === false) {
            return [
                'success' => false,
                'message' => $paymentStatus['message'],
                'data' => []
            ];
        }

        return ['success' => true];
    }

    /**
     * Block Cash on Delivery when any line ships via a seller-collected method.
     *
     * COD is only viable when a courier collects the cash and remits it to the
     * platform (so the platform can settle sellers normally). For seller-collected
     * methods (manual / local delivery / local pickup) the seller would hold the
     * cash and owe the platform its commission + fees — which we deliberately avoid.
     * This mirrors Amazon, where Self-Ship orders are prepaid-only and COD is offered
     * only on Amazon-fulfilled (Easy Ship / FBA) shipments.
     *
     * @param array $data
     * @param \Illuminate\Support\Collection $decisions
     * @return array
     */
    private function validateCodAgainstFulfillment(array $data, $decisions): array
    {
        if (strtolower((string) ($data['payment_type'] ?? '')) !== (string) PaymentTypeEnum::COD()) {
            return ['success' => true];
        }

        $codCapable = array_map('strval', FulfillmentMethodEnum::codCapable());

        $hasSellerCollected = $decisions->contains(function ($decision) use ($codCapable) {
            $method = (string) (($decision['zone']?->method) ?? FulfillmentMethodEnum::MANUAL());
            return !in_array($method, $codCapable, true);
        });

        if ($hasSellerCollected) {
            return [
                'success' => false,
                'message' => __('labels.cod_not_available_seller_shipped'),
                'data' => [],
            ];
        }

        return ['success' => true];
    }

    /**
     * Block Cash on Delivery when any product in the cart has COD turned off.
     *
     * Each product carries an `allow_cod` flag (seller-controlled). COD is an
     * all-or-nothing choice for the cart: a single disallowing item blocks it.
     *
     * @param array $data
     * @param \App\Models\Cart $cart
     * @return array
     */
    private function validateCodAgainstProducts(array $data, $cart): array
    {
        if (strtolower((string) ($data['payment_type'] ?? '')) !== (string) PaymentTypeEnum::COD()) {
            return ['success' => true];
        }

        $hasDisallowedItem = $cart->items->contains(function ($item) {
            return (string) ($item->product?->allow_cod ?? '1') === '0';
        });

        if ($hasDisallowedItem) {
            return [
                'success' => false,
                'message' => __('labels.cod_not_available_product'),
                'data' => [],
            ];
        }

        return ['success' => true];
    }

    /**
     * Block Cash on Delivery when the order total exceeds the market's COD cap.
     *
     * `max_cod_amount` is a flat, base-currency limit configured per market
     * (0 = no cap). Compared against the order total in base currency so it
     * matches how the cap is stored.
     *
     * @param array $data
     * @param \App\Models\Cart $cart
     * @return array
     */
    private function validateCodAgainstCap(array $data, $cart): array
    {
        if (strtolower((string) ($data['payment_type'] ?? '')) !== (string) PaymentTypeEnum::COD()) {
            return ['success' => true];
        }

        $market = request()?->attributes->get('market');
        if (!$market instanceof Market) {
            $market = Market::query()->where('is_default', true)->first();
        }

        $maxCodAmount = $market ? (float) ($market->max_cod_amount ?? 0) : 0.0;
        if ($maxCodAmount <= 0) {
            return ['success' => true];
        }

        $summary = app(CartService::class)->getPaymentSummary(
            $cart,
            $data['address'] ?? null,
            $this->usesWallet($data),
            $data['promo_code'] ?? null,
            $data['payment_type'] ?? null
        );

        if ((float) ($summary['order_total'] ?? 0) > $maxCodAmount) {
            return [
                'success' => false,
                'message' => __('labels.cod_not_available_max_amount'),
                'data' => [],
            ];
        }

        return ['success' => true];
    }

    /**
     * Single reading of the `use_wallet` flag. Clients send it as "1"/"0", as a
     * JSON boolean, or omit it entirely — all three must mean the same thing
     * here and in the payment summary, or the wallet gets applied to the total
     * without being debited.
     */
    private function usesWallet(array $data): bool
    {
        return filter_var($data['use_wallet'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * A wallet-method order has no gateway leg, so the wallet must settle the
     * whole payable amount. Anything left over would be captured as paid but
     * never collected.
     */
    private function validateWalletCoversTotal(array $data, $cart): array
    {
        if ((string) ($data['payment_type'] ?? '') !== (string) PaymentTypeEnum::WALLET()) {
            return ['success' => true];
        }

        if (!$this->usesWallet($data)) {
            return [
                'success' => false,
                'message' => __('labels.wallet_payment_requires_wallet_balance'),
                'data' => [],
            ];
        }

        $summary = app(CartService::class)->getPaymentSummary(
            $cart,
            $data['address'] ?? null,
            true,
            $data['promo_code'] ?? null,
            $data['payment_type'] ?? null
        );

        if (round((float) ($summary['payable_amount'] ?? 0), 2) > 0) {
            return [
                'success' => false,
                'message' => __('labels.insufficient_wallet_balance'),
                'data' => [
                    'order_total' => (float) ($summary['order_total'] ?? 0),
                    'wallet_balance' => (float) ($summary['wallet_balance'] ?? 0),
                    'shortfall' => round((float) ($summary['payable_amount'] ?? 0), 2),
                ],
            ];
        }

        return ['success' => true];
    }

    /**
     * Validate address and delivery zone
     *
     * @param User $user
     * @param array $data
     * @return array
     */
    private function validateAddress(User $user, array $data): array
    {
        $address = Address::where(['id' => $data['address_id'], 'user_id' => $user->id])->first();
        if (!$address) {
            return [
                'success' => false,
                'message' => __('labels.address_not_found'),
                'data' => []
            ];
        }
        $data['address'] = $address;

        if ($mismatch = $this->validateMarketMatchesAddress($address)) {
            return $mismatch;
        }

        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * The request market is client-supplied (X-Market header, ?market=, cookie),
     * so an order must not be priced in a market the delivery address does not
     * belong to. The storefront switches market when the address is chosen, so a
     * mismatch here means a stale client or a tampered request.
     */
    private function validateMarketMatchesAddress(Address $address): ?array
    {
        $requestMarket = request()?->attributes->get('market');

        if (!$requestMarket instanceof Market) {
            return null;
        }

        $countryCode = strtoupper(substr((string) ($address->country_code ?? ''), 0, 2));

        if ($countryCode === '') {
            return null;
        }

        // A country can belong to several markets, so the test is whether the
        // request market serves this country — not whether it is the top pick.
        if ($requestMarket->countries()->where('iso2', $countryCode)->exists()) {
            return null;
        }

        $addressMarket = app(MarketService::class)->byCountry($countryCode);

        if (!$addressMarket) {
            return null;
        }

        Log::warning('Order refused: request market does not serve the delivery address', [
            'address_id' => $address->id,
            'address_country' => $countryCode,
            'request_market' => $requestMarket->code,
            'address_market' => $addressMarket->code,
        ]);

        return [
            'success' => false,
            'message' => __('messages.market_address_mismatch'),
            'data' => []
        ];
    }

    /**
     * Validate stock and delivery availability
     *
     * @param Cart $cart
     * @param Address $address
     * @return array{success: bool, decisions?: Collection, message?: string, data?: array}
     */
    private function validateStockAndDeliveryAvailability(Cart $cart, Address $address, bool $cod = false): array
    {
        $stockVerification = $this->verifyCartItemsStock($cart);
        if ($stockVerification['success'] === false) {
            return $stockVerification;
        }

        $addonValidation = app(CartService::class)->validateCartAddonsForCheckout($cart);
        if ($addonValidation !== null) {
            return $addonValidation;
        }
        $totalsResult = $this->calculateCartTotals($cart);

        $itemsTotal = (float)$totalsResult['items_total'];
        $cartWeight = $cart->items->sum(fn($item) => (float)($item->variant->weight ?? 0.0) * (int)$item->quantity);

        $destination = [
            'country_iso2' => strtoupper(substr($address->country_code ?? '', 0, 2)),
            'state_code' => $address->state,
            'city_name' => $address->city,
            'pin_code' => $address->zipcode,
            'cart_total' => $itemsTotal,
            'cart_weight' => $cartWeight,
            'cod' => $cod,
        ];

        $router = app(FulfillmentRouterService::class);
        $decisions = $router->routeCart($cart->items, $destination);

        $unfulfillable = $decisions->whereNotNull('unfulfillable');

        if ($unfulfillable->isNotEmpty()) {
            $undeliverableItems = [];
            foreach ($unfulfillable as $d) {
                $cartItem = $cart->items->firstWhere('id', $d['cart_item_id']);
                if ($cartItem) {
                    $undeliverableItems[] = $cartItem->product->title . ' (' . ($d['unfulfillable'] ?? 'unfulfillable') . ')';
                }
            }

            return [
                'success' => false,
                'message' => __('labels.items_not_deliverable_to_address'),
                'data' => [
                    'undeliverable_items' => $undeliverableItems,
                ]
            ];
        }

        return [
            'success' => true,
            'decisions' => $decisions,
        ];
    }

    private function calculateCartTotals($cart)
    {
        return app(CartService::class)->calculateCartTotals($cart);
    }

    /**
     * Process order creation
     *
     * @param User $user
     * @param Cart $cart
     * @param array $data
     * @return Order
     * @throws Exception
     */
    private function processOrderCreation(User $user, Cart $cart, array $data): Order
    {
        return $this->createOrderFromCart($user, $cart, $data);
    }

    /**
     * Process payment transactions
     *
     * @param User $user
     * @param Order $order
     * @param array $data
     * @return array
     */
    private function processPaymentTransactions(User $user, Order $order, array $data): array
    {
        $walletDeductBase = ((float)$order->currency_rate) > 0
            ? round(((float)$order->wallet_balance) / (float)$order->currency_rate, 2)
            : (float)$order->wallet_balance;

        $walletTransaction = null;

        if ($this->usesWallet($data)) {
            $walletPaymentData = [
                'order_id' => $order->id,
                'amount' => $walletDeductBase,
                'description' => "Wallet balance of $order->currency_code $order->wallet_balance was used for Order #$order->id."
            ];
            $walletTransaction = WalletService::deductBalance($user->id, $walletPaymentData);
            if ($walletTransaction['success'] === false) {
                Log::error('Error deducting wallet balance: ' . $walletTransaction['message']);
                return ['success' => false, 'message' => $walletTransaction['message']];
            }
        }
        if ($data['payment_type'] == PaymentTypeEnum::WALLET() && ($walletTransaction['success'] ?? false)) {
            $paymentInfo = [
                'transaction_id' => "wallet_" . time(),
                'amount' => $order->wallet_balance,
                'currency' => $order->currency_code,
                'payment_method' => $data['payment_type'],
                'message' => 'Payment verification done',
                'payment_status' => PaymentStatusEnum::COMPLETED(),
            ];
            $this->paymentService->makeOrderPaymentTransaction($order, $paymentInfo, PaymentStatusEnum::COMPLETED());
            Order::capturePaymentFromOrder($order);
            OrderItem::capturePayment($order->id);
            return ['success' => true, 'message' => 'Wallet payment & order capture done'];
        }

        return ['success' => true, 'message' => 'Payment verification done'];
    }

    /**
     * Finalize order creation
     *
     * @param Order $order
     * @param Cart $cart
     * @param array $data
     * @param Collection $fulfillmentDecisions
     * @return array
     */
    private function supersedePriorPendingOrders(User $user): void
    {
        $priorOrders = Order::where('user_id', $user->id)
            ->where('status', OrderStatusEnum::OPEN())
            ->where('payment_status', PaymentStatusEnum::PENDING())
            ->where('stock_status', OrderInventoryService::STATUS_RESERVED)
            ->get();

        foreach ($priorOrders as $prior) {
            $this->cancelReservedOrder($prior);
        }
    }

    public function retryOrderPayment(User $user, string $orderSlug): array
    {
        $order = Order::where('slug', $orderSlug)->where('user_id', $user->id)->first();
        if (!$order) {
            return ['success' => false, 'message' => __('labels.order_not_found'), 'data' => []];
        }

        if ($order->status !== OrderStatusEnum::OPEN()
            || $order->payment_status !== PaymentStatusEnum::PENDING()
            || $order->stock_status !== OrderInventoryService::STATUS_RESERVED) {
            return ['success' => false, 'message' => __('labels.order_not_payable'), 'data' => []];
        }

        if (OrderPaymentTransaction::attemptCount($order->id) >= 3) {
            $this->cancelReservedOrder($order);
            return ['success' => false, 'message' => __('labels.payment_retry_limit_reached'), 'data' => []];
        }

        try {
            $postPayment = $this->paymentService->postPaymentInitialtion(order: $order, redirectUrl: null);
        } catch (Exception $e) {
            Log::error('Retry payment initiation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }

        $order->load(['items.product', 'items.variant', 'items.store', 'items.addons', 'items.sellerOrderItem.sellerOrder', 'user', 'sellerOrders.seller.user']);
        $order->payment_response = $postPayment['data'] ?? null;

        return ['success' => true, 'message' => __('labels.success'), 'data' => $order];
    }

    public function cancelOrderPayment(User $user, string $orderSlug): array
    {
        $order = Order::where('slug', $orderSlug)->where('user_id', $user->id)->first();
        if (!$order) {
            return ['success' => false, 'message' => __('labels.order_not_found'), 'data' => []];
        }

        if ($order->status !== OrderStatusEnum::OPEN()
            || $order->payment_status !== PaymentStatusEnum::PENDING()
            || $order->stock_status !== OrderInventoryService::STATUS_RESERVED) {
            return ['success' => false, 'message' => __('labels.order_not_payable'), 'data' => []];
        }

        $this->cancelReservedOrder($order);

        return ['success' => true, 'message' => __('labels.success'), 'data' => $order->fresh()];
    }

    public function cancelReservedOrder(Order $order): void
    {
        $order->refresh();

        if ($order->stock_status !== OrderInventoryService::STATUS_RESERVED
            || $order->payment_status !== PaymentStatusEnum::PENDING()) {
            return;
        }

        app(OrderInventoryService::class)->release($order);
        Order::refundWalletPortion($order);

        $order->payment_status = PaymentStatusEnum::FAILED();
        $order->status = OrderStatusEnum::CANCELLED();
        $order->cancellation_state = 'FULL';
        $order->save();

        OrderItem::paymentFailed($order->id);
    }

    private function reconcileItemPromoRounding(Order $order): void
    {
        $target = round((float) $order->promo_discount, 2);

        if ($target <= 0) {
            return;
        }

        $items = $order->items()->orderByDesc('promo_discount')->get();

        if ($items->isEmpty()) {
            return;
        }

        $delta = round($target - round((float) $items->sum('promo_discount'), 2), 2);

        if (abs($delta) < 0.01) {
            return;
        }

        $largest = $items->first();
        $largest->promo_discount = round(max(0, (float) $largest->promo_discount + $delta), 2);
        $largest->saveQuietly();
    }

    private function finalizeOrderCreation(Order $order, Cart $cart, $user, array $data, Collection $fulfillmentDecisions): array
    {
        $orderItemResponse = $this->createOrderItemsAndSellerOrders($order, $cart, $fulfillmentDecisions);
        if ($orderItemResponse['success'] === false) {
            return [
                'success' => false,
                'message' => $orderItemResponse['message'],
                'data' => []
            ];
        }

        $this->reconcileItemPromoRounding($order);

        $inventoryResult = app(OrderInventoryService::class)->applyOnCreation($order);
        if ($inventoryResult['success'] === false) {
            return [
                'success' => false,
                'message' => $inventoryResult['message'],
                'data' => []
            ];
        }

        $paymentResult = $this->processPaymentTransactions($user, $order, $data);
        if (!$paymentResult['success']) {
            return [
                'success' => false,
                'message' => $paymentResult['message'],
                'data' => []
            ];
        }

        DB::table('customer_pending_charges')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->update([
                'status' => 'applied',
                'applied_to_order_id' => $order->id,
                'applied_at' => now(),
            ]);

        $postPayment = $this->paymentService->postPaymentInitialtion(order: $order, redirectUrl: $data['redirect_url'] ?? null);

        $order->load(['items.product', 'items.variant', 'items.store', 'items.addons', 'items.sellerOrderItem.sellerOrder', 'user', 'sellerOrders.seller.user']);
        $order->payment_response = $postPayment['data'] ?? null;

        if ((string) $order->payment_method === (string) PaymentTypeEnum::COD()) {
            event(new OrderPlaced($order));
        }

        return [
            'success' => true,
            'order' => $order
        ];
    }

    private
        /**
         * @return array{market: ?Market, currency_code: string, rate: float}
         */
    function resolveOrderFxSnapshot(): array
    {
        $base = Currency::query()->where('is_base', true)->value('code') ?: 'USD';

        $market = request()?->attributes->get('market');
        if (!$market instanceof Market) {
            return ['market' => null, 'currency_code' => $base, 'rate' => 1.0];
        }

        if (strtoupper($market->currency_code) === strtoupper($base)) {
            return ['market' => $market, 'currency_code' => $market->currency_code, 'rate' => 1.0];
        }

        $rate = app(FxRateService::class)->convertCurrency(1.0, $base, $market->currency_code);

        return [
            'market' => $market,
            'currency_code' => $rate === null ? $base : $market->currency_code,
            'rate' => $rate ?? 1.0,
        ];
    }

    /**
     * Multiply a base-currency amount by the snapshotted order rate and round
     * to 2 dp. Centralised so every place that writes a monetary field on the
     * order family rounds identically — drifting cents between subtotal,
     * order_items.subtotal, and seller_orders.total_price was the bug.
     */
    protected function convertForOrder(float|int|string|null $amount, float $rate): float
    {
        return round(((float)$amount) * $rate, 2);
    }

    /**
     * @throws Exception
     */
    function createOrderFromCart(User $user, Cart $cart, array $data): mixed
    {
        $cartService = app(CartService::class);
        $paymentSummary = $cartService->getPaymentSummary($cart, $data['address'], $this->usesWallet($data), $data['promo_code'] ?? null, $data['payment_type'] ?? null);

        if ($paymentSummary['payable_amount'] < $data['minimumCartAmount'] && $data['payment_type'] !== PaymentTypeEnum::WALLET()) {
            throw new Exception(__('labels.minimum_cart_amount_not_met', ['amount' => $data['minimumCartAmount']]));
        }

        $fx = $this->resolveOrderFxSnapshot();
        $rate = $fx['rate'];
        $market = $fx['market'];

        $convertedTaxBreakdown = [];
        if (!empty($paymentSummary['tax_breakdown'])) {
            foreach ($paymentSummary['tax_breakdown'] as $tax) {
                $convertedTaxBreakdown[] = [
                    'tax_rate_id' => $tax['tax_rate_id'],
                    'title' => $tax['title'],
                    'rate' => $tax['rate'],
                    'amount' => $this->convertForOrder($tax['amount'], $rate),
                ];
            }
        }

        $orderSummary = [
            'base_currency' => [
                'items_total' => (float)$paymentSummary['items_total'],
                'delivery_charge' => (float)($paymentSummary['total_delivery_charges'] ?? 0.0),
                'promo_discount' => (float)($paymentSummary['promo_discount'] ?? 0.0),
                'wallet_amount_used' => (float)($paymentSummary['wallet_amount_used'] ?? 0.0),
                'tax_total' => (float)($paymentSummary['tax_total'] ?? 0.0),
                'payable_amount' => (float)$paymentSummary['payable_amount'],
                'order_total' => (float)$paymentSummary['order_total'],
            ],
            'fx_settings' => [
                'market_id' => $market?->id,
                'market_name' => $market?->name,
                'currency_code' => $fx['currency_code'],
                'currency_rate' => $rate,
                'currency_symbol' => $market ? PricePresenter::symbolFor($market->currency_code) : '₹',
                'format_rules' => $market ? PricePresenter::formatRules($market, $market->resolveDecimals()) : null,
            ],
            'converted_currency' => [
                'items_total' => $this->convertForOrder($paymentSummary['items_total'], $rate),
                'delivery_charge' => $this->convertForOrder($paymentSummary['total_delivery_charges'] ?? 0, $rate),
                'platform_fee' => $this->convertForOrder($paymentSummary['platform_fee'] ?? 0, $rate),
                'cod_fee' => $this->convertForOrder($paymentSummary['cod_fee'] ?? 0, $rate),
                'promo_discount' => $this->convertForOrder($paymentSummary['promo_discount'] ?? 0, $rate),
                'wallet_amount_used' => $this->convertForOrder($paymentSummary['wallet_amount_used'] ?? 0, $rate),
                'tax_total' => $this->convertForOrder($paymentSummary['tax_total'] ?? 0, $rate),
                'payable_amount' => $this->convertForOrder($paymentSummary['payable_amount'], $rate),
                'order_total' => $this->convertForOrder($paymentSummary['order_total'], $rate),
            ]
        ];

        $order = Order::create([
            'uuid' => Str::uuid()->toString(),
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'user_id' => $user->id,
            'slug' => Str::slug('order-' . time() . '-' . $user->id),
            'email' => $user->email ?? ($user->mobile ? $user->mobile . '@no-email.local' : 'guest@no-email.local'),
            'ip_address' => request()->ip(),
            'market_id' => $market?->id,
            'currency_code' => $fx['currency_code'],
            'currency_rate' => $rate,
            'payment_method' => $data['payment_type'],
            'payment_status' => PaymentStatusEnum::PENDING(),
            'wallet_balance' => $this->convertForOrder($paymentSummary['wallet_amount_used'] ?? 0, $rate),
            'promo_code' => $paymentSummary['promo_code'] ?? null,
            'promo_discount' => $this->convertForOrder($paymentSummary['promo_discount'] ?? 0, $rate),
            'delivery_charge' => $this->convertForOrder($paymentSummary['total_delivery_charges'] ?? 0, $rate),
            'platform_fee' => $this->convertForOrder($paymentSummary['platform_fee'] ?? 0, $rate),
            'cod_fee' => $this->convertForOrder($paymentSummary['cod_fee'] ?? 0, $rate),
            'subtotal' => $this->convertForOrder($paymentSummary['items_total'], $rate),
            'total_payable' => $this->convertForOrder($paymentSummary['payable_amount'], $rate),
            'final_total' => $this->convertForOrder($paymentSummary['order_total'], $rate),
            'status' => OrderStatusEnum::OPEN(),
            'billing_name' => $user->name,
            'billing_address_1' => $data['address']['address_line1'],
            'billing_address_2' => $data['address']['address_line2'],
            'billing_zip' => $data['address']['zipcode'],
            'billing_phone' => $data['address']['mobile'],
            'billing_address_type' => $data['address']['address_type'],
            'billing_city' => $data['address']['city'],
            'billing_state' => $data['address']['state'],
            'billing_country' => $data['address']['country'],
            'billing_country_code' => $data['address']['country_code'],

            'shipping_name' => $user->name,
            'shipping_address_1' => $data['address']['address_line1'],
            'shipping_address_2' => $data['address']['address_line2'],
            'shipping_zip' => $data['address']['zipcode'],
            'shipping_phone' => $data['address']['mobile'],
            'shipping_address_type' => $data['address']['address_type'],
            'shipping_city' => $data['address']['city'],
            'shipping_state' => $data['address']['state'],
            'shipping_country' => $data['address']['country'],
            'shipping_country_code' => $data['address']['country_code'],

            'order_note' => $data['order_note'] ?? '',

            'shipping_total' => $this->convertForOrder($paymentSummary['total_delivery_charges'] ?? 0, $rate),
            'tax_total' => $this->convertForOrder($paymentSummary['tax_total'] ?? 0, $rate),
            'tax_breakdown' => $convertedTaxBreakdown,
            'order_summary' => $orderSummary,
        ]);
        app(OrderNumberService::class)->assign($order);

        if (!empty($paymentSummary['promo_applied'])) {
            $claimed = Promo::where('id', $paymentSummary['promo_applied']['id'])
                ->where(function ($query) {
                    $query->whereNull('max_total_usage')
                        ->orWhere('max_total_usage', 0)
                        ->orWhereColumn('usage_count', '<', 'max_total_usage');
                })
                ->increment('usage_count');

            if ($claimed === 0) {
                throw new Exception(__('labels.promo_usage_limit_reached'));
            }

            OrderPromoLine::create([
                'order_id' => $order->id,
                'promo_id' => $paymentSummary['promo_applied']['id'],
                'promo_code' => $paymentSummary['promo_applied']['code'],
                'discount_amount' => $this->convertForOrder($paymentSummary['promo_discount'], $rate),
                'cashback_flag' => !($paymentSummary['promo_applied']['promo_mode'] === PromoModeEnum::INSTANT()),
                'is_awarded' => $paymentSummary['promo_applied']['promo_mode'] === PromoModeEnum::INSTANT(),
            ]);
        }
        return $order;
    }

    /**
     * Create order items and seller orders from cart items
     *
     * @param Order $order The order to create items for
     * @param Cart $cart The cart containing items
     * @param Collection $decisions Fulfillment routing decisions from validation
     * @return array
     */
    private function createOrderItemsAndSellerOrders(Order $order, Cart $cart, Collection $decisions): array
    {
        try {
            $decisionsByItem = $decisions->keyBy('cart_item_id');

            $itemsByStore = $cart->items->groupBy('store_id');

            foreach ($itemsByStore as $storeItems) {
                $this->processStoreItems($order, $storeItems, $decisionsByItem);
            }

            return [
                'success' => true,
                'message' => __('labels.success'),
                'data' => [],
            ];
        } catch (Exception $e) {
            Log::error('Error creating order items and seller orders', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => __('labels.order_creation_failed'),
                'data' => [],
            ];
        }
    }

    /**
     * @throws Exception
     */
    private function processStoreItems(Order $order, $storeItems, $decisionsByItem): void
    {
        $router = app(FulfillmentRouterService::class);
        $groups = $router->groupShipments($storeItems, $decisionsByItem->values());

        if ($groups->isEmpty()) {
            $firstDecision = $decisionsByItem->get($storeItems->first()->id);
            $groups = collect([[
                'location' => $firstDecision['location'] ?? null,
                'zone' => $firstDecision['zone'] ?? null,
                'location_id' => $firstDecision && $firstDecision['location'] ? (int)$firstDecision['location']->id : null,
                'zone_id' => $firstDecision && $firstDecision['zone'] ? (int)$firstDecision['zone']->id : null,
                'rate' => 0.0,
                'items' => $storeItems,
            ]]);
        }

        foreach ($groups as $group) {
            $this->createSellerOrderForShipment($order, $storeItems->first()->store, $group, $decisionsByItem);
        }
    }

    /**
     * Create and populate one seller order for a single shipment group
     * (origin location + zone). Each group carries its own location, zone and
     * shipping charge.
     *
     * @param array{location:mixed,zone:mixed,location_id:?int,zone_id:?int,rate:float,items:\Illuminate\Support\Collection} $group
     * @throws Exception
     */
    private function createSellerOrderForShipment(Order $order, $store, array $group, $decisionsByItem = null): void
    {
        $rate = (float)($order->currency_rate ?: 1);

        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $store->seller_id,
            'total_price' => 0,
            'market_id' => $order->market_id,
            'store_location_id' => $group['location_id'],
            'shipping_zone_id' => $group['zone_id'],
            'fulfillment_method' => $group['zone']?->method
                ?? FulfillmentMethodEnum::MANUAL(),
            'status' => $order->status instanceof \BackedEnum ? $order->status->value : (string)$order->status,
            'shipping_charge_applied' => $this->convertForOrder((float)$group['rate'], $rate),
        ]);

        $storeTotalPrice = 0.0;
        $storeTotalTax = 0.0;
        $storeTotalPromoShare = 0.0;

        foreach ($group['items'] as $cartItem) {
            $decision = $decisionsByItem ? $decisionsByItem->get($cartItem->id) : null;
            $result = $this->processCartItem($order, $cartItem, $sellerOrder, $storeTotalPrice, $storeTotalTax, $storeTotalPromoShare, $decision);
            if ($result !== true) {
                throw new Exception($result['message']);
            }
        }

        $sellerOrder->update([
            'total_price' => $this->convertForOrder($storeTotalPrice, $rate),
            'gross_subtotal' => $this->convertForOrder($storeTotalPrice, $rate),
            'tax_amount' => $this->convertForOrder($storeTotalTax, $rate),
            'promo_share' => $storeTotalPromoShare,
        ]);
    }

    private function processCartItem(
        Order       $order,
                    $cartItem,
        SellerOrder $sellerOrder,
                    &$storeTotalPrice,
                    &$storeTotalTax,
                    &$storeTotalPromoShare,
                    $decision = null
    ): true|array
    {
        $storeVariant = $this->getStoreVariant($cartItem);

        $availableStock = $storeVariant->stock;
        if ($availableStock !== null && $availableStock < $cartItem->quantity) {
            return [
                'success' => false,
                'message' => ucfirst($storeVariant->productVariant->title ?? '')
                    . " " . __('messages.product_variant_not_available_in_store'),
                'data' => [],
            ];
        }

        [$subtotal, $adminCommissionAmount, $promoDiscount, $taxPercent] =
            $this->calculatePricing($order, $cartItem, $storeVariant);

        $storeTotalPrice += $subtotal;

        $taxAmount = (float)$storeVariant->special_price - (float)$storeVariant->special_price_exclude_tax;
        $lineTaxAmount = $taxAmount * $cartItem->quantity;
        $storeTotalTax += $lineTaxAmount;

        $orderItem = $this->createOrderItem($order, $cartItem, $storeVariant, $subtotal, $adminCommissionAmount, $promoDiscount, $taxPercent, $decision);

        $cartItem->product?->loadMissing('comboPack.items.product', 'comboPack.items.variant');
        $kitComponents = $cartItem->product?->comboPack?->items ?? collect();
        if ($cartItem->product?->type === 'kit' && $kitComponents->isEmpty()) {
            return ['success' => false, 'message' => __('labels.combo_pack_components_required'), 'data' => []];
        }
        if ($kitComponents->isNotEmpty()) {
            foreach ($kitComponents as $component) {
                OrderItemComboComponent::create([
                    'order_item_id' => $orderItem->id,
                    'product_id' => $component->product_id,
                    'product_variant_id' => $component->product_variant_id,
                    'product_title' => $component->product?->title ?? '',
                    'variant_title' => $component->variant?->title ?? '',
                    'quantity' => (int) $component->quantity,
                ]);
            }
        }

        $rate = (float)($order->currency_rate ?: 1);
        $storeTotalPromoShare += $this->convertForOrder($promoDiscount, $rate);

        $this->snapshotOrderItemAddons($orderItem, $cartItem);

        $this->attachRequiredOrderItemAttachments($orderItem, $cartItem);
        $this->createSellerOrderItem($sellerOrder, $cartItem, $orderItem, $storeVariant);

        return true;
    }

    private function snapshotOrderItemAddons(OrderItem $orderItem, $cartItem): void
    {
        $addons = $cartItem->relationLoaded('addons')
            ? $cartItem->getRelation('addons')
            : $cartItem->addons()->get();

        if (!$addons || $addons->isEmpty()) {
            return;
        }

        $rate = (float)($orderItem->order?->currency_rate ?: 1);

        foreach ($addons as $addon) {
            /** @var CartItemAddon $addon */
            OrderItemAddon::create([
                'order_item_id' => $orderItem->id,
                'addon_group_id' => (int)$addon->addon_group_id,
                'addon_item_id' => (int)$addon->addon_item_id,
                'price' => $this->convertForOrder($addon->price, $rate),
                'metadata' => $addon->metadata,
            ]);
        }
    }

    /**
     * Attach uploaded files to order item when the product requires attachments.
     */
    private function attachRequiredOrderItemAttachments(OrderItem $orderItem, $cartItem): void
    {
        try {
            $product = $cartItem->product;
            if (!$product) {
                return;
            }
            $requires = (string)$product->is_attachment_required === '1' || $product->is_attachment_required === 1 || $product->is_attachment_required === true;
            if (!$requires) {
                return;
            }

            $productId = (string)$product->id;
            $request = request();
            $files = $request->file("attachments.$productId");
            if (!$files) {
                $files = $request->file("attchment.$productId");
            }
            if (!$files) {
                return;
            }
            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                if ($file) {
                    $orderItem->addMedia($file)
                        ->toMediaCollection(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS());
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to attach order item attachments', [
                'order_item_id' => $orderItem->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private
    function getStoreVariant($cartItem)
    {
        return $cartItem->variant->storeProductVariants
            ->where('store_id', $cartItem->store->id)
            ->first();
    }

    /**
     * Effective per-unit selling price (pre-tax) for the line. Uses the
     * special price when one is set, otherwise the regular price — mirroring
     * CartService::calculateCartTotals so a product without a special price
     * isn't stamped with a 0 price on order_items / seller_order_items.
     */
    private function effectiveUnitPrice($storeVariant): float
    {
        $special = (float)($storeVariant->special_price_exclude_tax ?? 0);
        if ($special > 0) {
            return $special;
        }
        return (float)($storeVariant->price_exclude_tax ?? 0);
    }

    private
    function calculatePricing(Order $order, $cartItem, $storeVariant): array
    {
        $commission = $storeVariant->category_commission->commission ?? 0;
        $specialPrice = $storeVariant->special_price_exclude_tax;
        $specialPriceWithTax = $storeVariant->special_price;

        $addonsPerUnit = $this->sumCartAddonPrices($cartItem);
        $addonsLineTotal = (float)$cartItem->quantity * $addonsPerUnit;

        $productSubtotal = $cartItem->quantity * $specialPriceWithTax;
        $subtotal = $productSubtotal + $addonsLineTotal;

        $productSubtotalExclTax = $cartItem->quantity * $specialPrice;
        $commissionBase = $productSubtotalExclTax + $addonsLineTotal;

        $adminCommissionAmount = $commissionBase * $commission / 100;

        $taxPercent = StoreProductVariant::scopeTaxPercentage($specialPrice, $specialPriceWithTax);

        $promoDiscount = 0;
        if (!empty($order['promo_code']) && (float)$order->subtotal > 0) {
            $promoDiscount = ((float)$subtotal / (float)$order->subtotal) * $order->promo_discount;
        }

        return [$subtotal, $adminCommissionAmount, $promoDiscount, $taxPercent];
    }

    /**
     * Sum the per-unit price snapshot of every addon attached to a cart line.
     */
    private function sumCartAddonPrices($cartItem): float
    {
        $addons = $cartItem->relationLoaded('addons')
            ? $cartItem->getRelation('addons')
            : $cartItem->addons()->get();

        if (!$addons || $addons->isEmpty()) {
            return 0.0;
        }

        return (float)$addons->sum(fn($a) => (float)$a->price);
    }

    private
    function createOrderItem(Order $order, $cartItem, $storeVariant, $subtotal, $adminCommissionAmount, $promoDiscount, $taxPercent, $decision = null): OrderItem
    {
        $rate = (float)($order->currency_rate ?: 1);

        $taxAmount = (float)$storeVariant->special_price - (float)$storeVariant->special_price_exclude_tax;

        $etaMin = $decision['eta_min'] ?? null;
        $etaMax = $decision['eta_max'] ?? null;
        $etaUnit = $decision['eta_unit'] ?? null;

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $cartItem->product_id,
            'product_variant_id' => $cartItem->product_variant_id,
            'store_id' => $cartItem->store_id,
            'title' => $cartItem->product->title,
            'variant_title' => $cartItem->variant->title,
            'gift_card_discount' => 0,
            'admin_commission_amount' => $this->convertForOrder($adminCommissionAmount, $rate),
            'seller_commission_amount' => 0,
            'commission_settled' => '0',
            'return_eligible' => $cartItem->product->is_returnable == "1" ? '1' : '0',
            'returnable_days' => $cartItem->product->returnable_days ?? 0,
            'return_deadline' => $this->getReturnDeadline( $cartItem->product->returnable_days ?? 0),
            'is_cancelable' => $cartItem->product->is_cancelable == "1",
            'cancelable_till' => $cartItem->product->cancelable_till,
            'discounted_price' => 0,
            'promo_discount' => $this->convertForOrder($promoDiscount, $rate),
            'discount' => 0,
            'tax_amount' => $this->convertForOrder($taxAmount, $rate),
            'tax_percent' => (float)$taxPercent,
            'sku' => $storeVariant->sku ?? "N/A",
            'quantity' => (float)$cartItem->quantity,
            'price' => $this->convertForOrder($this->effectiveUnitPrice($storeVariant), $rate),
            'subtotal' => $this->convertForOrder($subtotal, $rate),
            'eta_min' => $etaMin !== null ? (int)$etaMin : null,
            'eta_max' => $etaMax !== null ? (int)$etaMax : null,
            'eta_unit' => $etaUnit,
            'status' => $this->determineOrderItemStatus($order),
        ]);
    }

    private
    function createSellerOrderItem(SellerOrder $sellerOrder, $cartItem, OrderItem $orderItem, $storeVariant): void
    {
        $rate = (float)($sellerOrder->order?->currency_rate ?: 1);

        SellerOrderItem::create([
            'seller_order_id' => $sellerOrder->id,
            'product_id' => $cartItem->product_id,
            'product_variant_id' => $cartItem->product_variant_id,
            'order_item_id' => $orderItem->id,
            'quantity' => (float)$cartItem->quantity,
            'price' => $this->convertForOrder($this->effectiveUnitPrice($storeVariant), $rate),
        ]);
    }

    private
    function determineOrderItemStatus(Order $order): string
    {
        return (string)OrderItemStatusEnum::ACTIVE();
    }

    /**
     * Verify stock availability for all cart items before creating order
     *
     * @param Cart $cart The cart to verify stock for
     * @return array Result containing success status, message, and data
     */
    function verifyCartItemsStock(Cart $cart): array
    {
        try {
            $outOfStockItems = [];

            foreach ($cart->items as $cartItem) {
                $storeVariant = $this->getStoreVariant($cartItem);

                if (!$storeVariant) {
                    $outOfStockItems[] = [
                        'product' => $cartItem->product->title ?? 'Unknown Product',
                        'variant' => $cartItem->variant->title ?? 'Unknown Variant',
                        'store' => $cartItem->store->name ?? 'Unknown Store',
                        'requested_quantity' => $cartItem->quantity,
                        'available_stock' => 0,
                        'message' => 'Product variant not available in store'
                    ];
                    continue;
                }

                $availableStock = $storeVariant->stock;
                if ($availableStock !== null && $availableStock < $cartItem->quantity) {
                    $outOfStockItems[] = [
                        'product' => $cartItem->product->title ?? 'Unknown Product',
                        'variant' => $cartItem->variant->title ?? 'Unknown Variant',
                        'store' => $cartItem->store->name ?? 'Unknown Store',
                        'requested_quantity' => $cartItem->quantity,
                        'available_stock' => $availableStock,
                        'message' => 'Insufficient stock available'
                    ];
                }
            }

            if (!empty($outOfStockItems)) {
                $itemNames = array_map(function ($item) {
                    return $item['product'] . ($item['variant'] && $item['variant'] !== $item['product'] ? ' (' . $item['variant'] . ')' : '');
                }, $outOfStockItems);

                $message = count($outOfStockItems) === 1
                    ? $itemNames[0] . ' is not available in sufficient quantity.'
                    : 'The following items are not available in sufficient quantity: ' . implode(', ', $itemNames);

                return [
                    'success' => false,
                    'message' => $message,
                    'data' => [
                        'out_of_stock_items' => $outOfStockItems
                    ]
                ];
            }

            return [
                'success' => true,
                'message' => 'All items have sufficient stock',
                'data' => []
            ];

        } catch (Exception $e) {
            Log::error('Error verifying cart items stock', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => []
            ];
        }
    }


    /**
     * Get order details
     *
     * @param User $user The user requesting the order
     * @param string $orderSlug The order slug
     * @return array Result containing success status, message, and order data
     */
    public
    function getOrder(User $user, string $orderSlug): array
    {
        try {
            $order = Order::where('user_id', $user->id)
                ->where('slug', $orderSlug)
                ->with(['items.product', 'items.product.category', 'items.product.brand', 'items.variant', 'items.store', 'items.order', 'items.sellerOrderItem', 'items.comboComponents', 'items.shipmentItems.shipment.statusEvents', 'sellerFeedbacks', 'sellerOrders', 'items.returns', 'promoLine', 'paymentTransactions', 'refunds.items'])
                ->first();

            if (!$order) {
                return [
                    'success' => false,
                    'message' => __('messages.order_not_found'),
                    'data' => []
                ];
            }

            return [
                'success' => true,
                'message' => __('messages.order_retrieved_successfully'),
                'data' => $order
            ];

        } catch (Exception $e) {
            Log::error('Error retrieving order: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => []
            ];
        }
    }

    /**
     * Get user's orders
     *
     * @param User $user The user requesting their orders
     * @return array Result containing success status, message, and orders data
     */
    public function getUserOrders(User $user, $perPage = 15, $filters = null): array
    {
        try {
            $query = Order::where('user_id', $user->id)
                ->whereNot(fn ($q) => $q
                    ->where('status', OrderStatusEnum::CANCELLED())
                    ->where('payment_status', PaymentStatusEnum::FAILED()))
                ->with([
                    'items.product',
                    'items.variant',
                    'items.comboComponents',
                    'items.store',
                    'items.store.seller',
                    'sellerFeedbacks',
                    'sellerOrders',
                    'sellerOrders.shipments.items.orderItem.product',
                    'sellerOrders.shipments.items.orderItem.variant',
                    'sellerOrders.shipments.statusEvents',
                    'items.shipmentItems.shipment.statusEvents',
                    'items.returns',
                    'items.sellerOrderItem',
                    'refunds.items',
                    'promoLine',
                    'paymentTransactions',
                ]);

            if ($filters['date_range']) {
                $fromDate = DateRangeFilterEnum::fromDate($filters['range']);
                if ($fromDate) {
                    $query->where('created_at', '>=', $fromDate);
                }
            }

            if ($filters['status']) {
                $query->where('status', $filters['status']);
            }

            $orders = $query
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return [
                'success' => true,
                'message' => __('messages.orders_retrieved_successfully'),
                'data' => $orders
            ];

        } catch (Exception $e) {
            Log::error('Error retrieving user orders: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => []
            ];
        }
    }

    /**
     * Customer "My Orders" — returned as a FLAT ITEM LIST: one entry per order
     * item (with its order context), paginated per item.
     */
    public function getUserOrderItems(User $user, $perPage = 15, $filters = null): array
    {
        try {
            $query = OrderItem::whereHas('order', fn ($q) => $q
                ->where('user_id', $user->id)
                ->whereNot(fn ($sub) => $sub
                    ->where('status', OrderStatusEnum::CANCELLED())
                    ->where('payment_status', PaymentStatusEnum::FAILED())))
                ->with([
                    'order.market.currency',
                    'order.refunds.items',
                    'product',
                    'product.category',
                    'product.brand',
                    'variant',
                    'comboComponents',
                    'store.seller.user',
                    'returns',
                    'shipmentItems.shipment',
                ])
                ->orderByDesc('id');

            if (!empty($filters['date_range'])) {
                $fromDate = DateRangeFilterEnum::fromDate($filters['date_range']);
                if ($fromDate) {
                    $query->whereHas('order', fn ($q) => $q->where('created_at', '>=', $fromDate));
                }
            }

            if (!empty($filters['status'])) {
                $this->applyCustomerItemStatusFilter($query, (string) $filters['status']);
            }

            $items = $query->paginate($perPage);

            return [
                'success' => true,
                'message' => __('messages.orders_retrieved_successfully'),
                'data' => $items,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving user order items: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Translate a customer-facing item status code (what the shopper sees and
     * filters by, produced by OrderCustomerStatusPresenter::itemStatus()) into
     * the underlying query constraints on the order_items row. The raw
     * `status` column only holds active/cancelled/returned/refunded, so a naive
     * `where('status', $code)` matched nothing for processing/shipped/delivered/
     * on_hold/return_requested. An unknown code returns no rows rather than
     * silently ignoring the filter.
     */
    private function applyCustomerItemStatusFilter($query, string $code): void
    {
        $activeReturnStatuses = [
            (string) OrderItemReturnStatusEnum::REQUESTED(),
            (string) OrderItemReturnStatusEnum::APPROVED(),
            (string) OrderItemReturnStatusEnum::IN_TRANSIT(),
            (string) OrderItemReturnStatusEnum::RECEIVED(),
        ];
        $withActiveReturn = fn ($q) => $q->whereHas('returns', fn ($r) => $r->whereIn('return_status', $activeReturnStatuses));
        $withoutActiveReturn = fn ($q) => $q->whereDoesntHave('returns', fn ($r) => $r->whereIn('return_status', $activeReturnStatuses));

        switch ($code) {
            case (string) CustomerItemStatusEnum::CANCELLED():
                $query->where('status', (string) OrderItemStatusEnum::CANCELLED());
                break;

            case (string) CustomerItemStatusEnum::REFUNDED():
                $query->where('status', (string) OrderItemStatusEnum::REFUNDED());
                break;

            case (string) CustomerItemStatusEnum::RETURNED():
                $query->where('status', (string) OrderItemStatusEnum::RETURNED());
                break;

            case (string) CustomerItemStatusEnum::RETURN_REQUESTED():
                $query->where('status', (string) OrderItemStatusEnum::ACTIVE())
                    ->where($withActiveReturn);
                break;

            case (string) CustomerItemStatusEnum::DELIVERED():
                $query->where('status', (string) OrderItemStatusEnum::ACTIVE())
                    ->where('fulfillment_status', (string) FulfillmentStatusEnum::FULFILLED())
                    ->where($withoutActiveReturn);
                break;

            case (string) CustomerItemStatusEnum::SHIPPED():
                $query->where('status', (string) OrderItemStatusEnum::ACTIVE())
                    ->where($withoutActiveReturn)
                    ->whereNotIn('fulfillment_status', [
                        (string) FulfillmentStatusEnum::FULFILLED(),
                        (string) FulfillmentStatusEnum::ON_HOLD(),
                    ])
                    ->where(function ($q) {
                        $q->where('fulfillment_status', (string) FulfillmentStatusEnum::PARTIALLY_FULFILLED())
                          ->orWhereHas('shipmentItems.shipment', function ($sub) {
                              $sub->where('is_return', false)
                                  ->where('status', '!=', (string) ShipmentStatusEnum::CANCELLED())
                                  ->whereIn('status', [
                                      (string) ShipmentStatusEnum::OUT_FOR_DELIVERY(),
                                      (string) ShipmentStatusEnum::ATTEMPTED_DELIVERY(),
                                      (string) ShipmentStatusEnum::IN_TRANSIT(),
                                      (string) ShipmentStatusEnum::CONFIRMED(),
                                  ]);
                          });
                    });
                break;

            case (string) CustomerItemStatusEnum::ON_HOLD():
                $query->where('status', (string) OrderItemStatusEnum::ACTIVE())
                    ->where('fulfillment_status', (string) FulfillmentStatusEnum::ON_HOLD())
                    ->where($withoutActiveReturn);
                break;

            case (string) CustomerItemStatusEnum::PROCESSING():
                $query->where('status', (string) OrderItemStatusEnum::ACTIVE())
                    ->where($withoutActiveReturn)
                    ->whereNotIn('fulfillment_status', [
                        (string) FulfillmentStatusEnum::FULFILLED(),
                        (string) FulfillmentStatusEnum::PARTIALLY_FULFILLED(),
                        (string) FulfillmentStatusEnum::ON_HOLD(),
                    ])
                    ->whereDoesntHave('shipmentItems.shipment', function ($sub) {
                        $sub->where('is_return', false)
                            ->where('status', '!=', (string) ShipmentStatusEnum::CANCELLED())
                            ->whereIn('status', [
                                (string) ShipmentStatusEnum::OUT_FOR_DELIVERY(),
                                (string) ShipmentStatusEnum::ATTEMPTED_DELIVERY(),
                                (string) ShipmentStatusEnum::IN_TRANSIT(),
                                (string) ShipmentStatusEnum::CONFIRMED(),
                            ]);
                    });
                break;

            default:
                $query->whereRaw('1 = 0');
                break;
        }
    }

    function getReturnDeadline(int $days, ?string $fromDate = null): string
    {
        $baseDate = $fromDate ? Carbon::parse($fromDate) : Carbon::now();
        return $baseDate->addDays($days)->format('Y-m-d');
    }

    /**
     * Determine the overall order status based on the statuses of all order items
     *
     * @param array $orderItemsStatuses Array of order item statuses
     * @return string The determined order status
     */

    /**
     * Recalculate order amounts after an item is rejected
     *
     * @param int $orderId The order ID
     * @param \App\Models\OrderItem|null $rejectedItem The rejected order item (optional)
     * @return array Result containing success status, message, and data
     */
    public
    function recalculateOrderAmounts(int $orderId, $rejectedItem = null): array
    {
        $order = Order::find($orderId);
        if (!$order) {
            return ['success' => false, 'message' => __('messages.order_not_found') ?? 'Order not found', 'data' => []];
        }

        $this->recomputeOrderMoneyFromItems($order);

        $fresh = $order->fresh();
        return [
            'success' => true,
            'message' => 'Order amounts recalculated successfully',
            'data' => [
                'order_id' => $orderId,
                'new_subtotal' => (float) $fresh->subtotal,
                'new_final_total' => (float) $fresh->final_total,
                'new_total_payable' => (float) $fresh->total_payable,
            ],
        ];
    }

    /**
     * Cancel an order item
     *
     * @param User $user The user requesting the cancellation
     * @param int $orderItemId The order item ID to cancel
     * @return array Result containing success status, message, and data
     */
    public
    function cancelOrderItem(User $user, int $orderItemId): array
    {
        try {
            DB::beginTransaction();

            $orderItem = OrderItem::with(['order', 'product'])
                ->where('id', $orderItemId)
                ->whereHas('order', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->first();

            if (!$orderItem) {
                return [
                    'success' => false,
                    'message' => __('messages.order_item_not_found'),
                    'data' => []
                ];
            }

            if (!$orderItem->is_cancelable) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => __('messages.product_not_cancelable'),
                    'data' => []
                ];
            }

            if ((string)$orderItem->status !== (string)OrderItemStatusEnum::ACTIVE()) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => __('messages.order_item_already_in_terminal_state'),
                    'data' => []
                ];
            }

            $this->lockCancellationItems([(int) $orderItem->id]);
            $orderItem->refresh();
            if (!app(FulfillmentRollupService::class)->cancellationForItem($orderItem, true)['allowed']) {
                DB::rollBack();
                return ['success' => false, 'message' => __('messages.order_item_cannot_be_cancelled_at_current_status'), 'data' => []];
            }
            app(ShipmentService::class)->releaseItemFromPendingParcels((int) $orderItem->id);
            $orderItem->refresh();

            $orderedQty = (int) $orderItem->quantity;
            $cancellation = app(FulfillmentRollupService::class)->cancellationForItem($orderItem, true);
            $unshippedQty = $cancellation['quantity'];

            if ($unshippedQty <= 0) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => __('messages.order_item_cannot_be_cancelled_at_current_status'),
                    'data' => []
                ];
            }

            $isPartial = $unshippedQty < $orderedQty;

            $oldStatus = $orderItem->status;

            if ($isPartial) {
                $perUnitSub    = $orderedQty > 0 ? (float)$orderItem->subtotal / $orderedQty : 0.0;
                $perUnitTax    = $orderedQty > 0 ? (float)$orderItem->tax_amount / $orderedQty : 0.0;
                $perUnitPromo  = $orderedQty > 0 ? (float)$orderItem->promo_discount / $orderedQty : 0.0;

                $cancelQty = $unshippedQty;
                $keepQty   = $committedQty;
                $refundForCancelled = round(($perUnitSub - $perUnitPromo) * $cancelQty, 2);

                $orderItem->quantity       = $keepQty;
                $orderItem->subtotal       = round($perUnitSub * $keepQty, 2);
                $orderItem->tax_amount     = round($perUnitTax * $keepQty, 2);
                $orderItem->promo_discount = round($perUnitPromo * $keepQty, 2);
                $orderItem->save();

                $restockResult = app(OrderInventoryService::class)->restock($orderItem, $cancelQty, "Customer cancelled {$cancelQty} unshipped unit(s) of order item #{$orderItem->id}.");
                if (($restockResult['success'] ?? false) === false) {
                    DB::rollBack();
                    return $restockResult;
                }

                $refundResult = $this->processOrderItemRefund($orderItem, 'cancel', $refundForCancelled, $cancelQty);
            } else {
                $orderItem->update([
                    'status' => OrderItemStatusEnum::CANCELLED(),
                    'cancel_reason' => CancelReasonEnum::CUSTOMER(),
                ]);

                event(new OrderStatusUpdated(
                    orderItem: $orderItem,
                    oldStatus: $oldStatus,
                    newStatus: OrderItemStatusEnum::CANCELLED(),
                    source: 'customer',
                ));

                $refundResult = $this->processOrderItemRefund($orderItem);
            }

            if (!$refundResult['success']) {
                DB::rollBack();
                return $refundResult;
            }

            $this->recomputeOrderMoneyFromItems($orderItem->order);
            $this->updateOrderStatusAfterCancellation($orderItem->order->fresh());

            DB::commit();

            Log::info('Order item cancelled successfully', [
                'order_item_id' => $orderItem->id,
                'order_id' => $orderItem->order_id,
                'user_id' => $user->id,
                'old_status' => $oldStatus,
                'refund_amount' => $refundResult['data']['refund_amount'] ?? 0
            ]);

            return [
                'success' => true,
                'message' => __('messages.order_item_cancelled_successfully'),
                'data' => [
                    'order_item' => $orderItem->fresh(),
                    'refund_details' => $refundResult['data'] ?? null
                ]
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error cancelling order item', [
                'order_item_id' => $orderItemId,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => []
            ];
        }
    }

    /**
     * Cancel an order item the seller had already accepted (or started
     * preparing). Pre-collect only — once a rider has collected the item, the
     * rider/admin flow owns it.
     *
     * Side effects: refund (when applicable), restock (via the listener),
     * order-amount recalculation, order-status recompute, rider unassignment
     * if no live items remain, increment seller.post_accept_cancel_count.
     *
     * @param int $orderItemId Item id (matches OrderItem.id, not SellerOrderItem.id)
     * @param int $sellerId Seller making the cancellation
     * @param string $reason Free-text reason provided by the seller
     * @return array{success: bool, message: string, data: array}
     */
    public function cancelOrderItemBySeller(int $orderItemId, int $sellerId, string $reason): array
    {
        try {
            DB::beginTransaction();

            $sellerOrderItem = SellerOrderItem::where('order_item_id', $orderItemId)
                ->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $sellerId))
                ->with(['sellerOrder.seller', 'orderItem.order'])
                ->first();

            if (!$sellerOrderItem || !$sellerOrderItem->orderItem) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => __('labels.order_item_not_found'),
                    'data' => [],
                ];
            }

            $orderItem = $sellerOrderItem->orderItem;
            $currentStatus = $orderItem->status;

            $this->lockCancellationItems([(int) $orderItem->id]);
            $orderItem->refresh();
            $eligibility = app(FulfillmentRollupService::class)->cancellationForItem($orderItem);
            if (!$eligibility['allowed'] || $eligibility['quantity'] < (int) $orderItem->quantity) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.order_item_cannot_be_cancelled_at_current_status'), 'data' => []];
            }
            app(ShipmentService::class)->releaseItemFromPendingParcels((int) $orderItem->id);
            $orderItem->refresh();

            $cancellation = app(FulfillmentRollupService::class)->cancellationForItem($orderItem);
            if (!$cancellation['allowed'] || $cancellation['quantity'] < (int) $orderItem->quantity) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => __('labels.order_item_cannot_be_cancelled_at_current_status'),
                    'data' => ['current_status' => $currentStatus],
                ];
            }

            $orderItem->update([
                'status' => OrderItemStatusEnum::CANCELLED(),
                'cancellation_reason' => $reason,
                'cancel_reason' => CancelReasonEnum::STAFF(),
            ]);

            event(new OrderStatusUpdated(
                orderItem: $orderItem,
                oldStatus: $currentStatus,
                newStatus: OrderItemStatusEnum::CANCELLED(),
                source: 'seller',
            ));

            $refundResult = $this->processOrderItemRefund($orderItem, type: 'cancel');
            if (!$refundResult['success']) {
                DB::rollBack();
                return $refundResult;
            }

            $this->recomputeOrderMoneyFromItems($orderItem->order);

            $order = $orderItem->order->fresh();
            $this->updateOrderStatusAfterCancellation($order);

            $sellerOrderItem->sellerOrder->seller?->increment('post_accept_cancel_count');

            DB::commit();

            Log::info('Seller cancelled order item post-accept', [
                'order_item_id' => $orderItem->id,
                'seller_id' => $sellerId,
                'old_status' => $currentStatus,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'message' => __('labels.order_item_cancelled_successfully'),
                'data' => [
                    'order_item' => $orderItem->fresh(),
                    'refund_details' => $refundResult['data'] ?? null,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Seller post-accept cancellation failed', [
                'order_item_id' => $orderItemId,
                'seller_id' => $sellerId,
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
     * Force any item status transition. Bypasses the normal isValidStatusTransition
     * guard. Use sparingly — admin escape hatch for stuck orders.
     *
     * @return array{success: bool, message: string, data: array}
     */
    public function updateOrderStatusByAdmin(int $orderItemId, string $newStatus, int $adminId, string $reason): array
    {
        try {
            DB::beginTransaction();

            $orderItem = OrderItem::with(['order'])->find($orderItemId);
            if (!$orderItem) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.order_item_not_found'), 'data' => []];
            }

            $oldStatus = $orderItem->status;
            $orderItem->status = $newStatus;
            $orderItem->saveQuietly();

            event(new OrderStatusUpdated(
                orderItem: $orderItem,
                oldStatus: $oldStatus,
                newStatus: $newStatus,
                source: 'admin',
            ));

            $this->writeAuditLog(
                orderId: $orderItem->order_id,
                orderItemId: $orderItem->id,
                adminId: $adminId,
                action: 'force_status',
                oldValue: ['status' => $oldStatus],
                newValue: ['status' => $newStatus],
                reason: $reason,
            );

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.order_status_force_updated'),
                'data' => ['order_item' => $orderItem->fresh()],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Admin force-status failed', [
                'order_item_id' => $orderItemId, 'admin_id' => $adminId, 'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }
    }

    /**
     * Admin cancel screen — cancel whole or partial quantities across multiple
     * lines in one action. Pre-fulfillment only (a delivered/shipped line must
     * go through returns). Restock is per-line and optional. NO refund is issued
     * here: the refund owed is shown on the screen and processed separately from
     * the Refund flow.
     *
     * @param array<int,array{order_item_id:int,cancel_qty:int,restock:bool}> $lines
     * @return array{success:bool,message:string,data:array}
     */
    public function cancelOrderLinesByAdmin(int $orderId, int $adminId, array $lines, string $reason): array
    {
        try {
            DB::beginTransaction();

            $order = Order::with('items')->find($orderId);
            if (!$order) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.order_not_found'), 'data' => []];
            }

            if ((string)$order->status === (string)OrderStatusEnum::ARCHIVED()) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.order_archived_reopen_first'), 'data' => []];
            }

            $requestedIds = collect($lines)->pluck('order_item_id')->map(fn ($id) => (int) $id)->unique();
            $ownedIds = $order->items->pluck('id');
            if ($requestedIds->count() !== count($lines) || $requestedIds->diff($ownedIds)->isNotEmpty()) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.order_item_not_found'), 'data' => []];
            }
            $this->lockCancellationItems($requestedIds->all());
            $order->load('items.shipmentItems.shipment.statusEvents');
            foreach ($lines as $line) {
                $item = $order->items->firstWhere('id', (int) $line['order_item_id']);
                $eligibility = app(FulfillmentRollupService::class)->cancellationForItem($item);
                if ((int) ($line['cancel_qty'] ?? 0) > 0 && (!$eligibility['allowed'] || (int) $line['cancel_qty'] > $eligibility['quantity'])) {
                    DB::rollBack();
                    return ['success' => false, 'message' => __('labels.order_item_cannot_be_cancelled_at_current_status'), 'data' => ['order_item_id' => $item->id]];
                }
            }
            foreach ($lines as $line) {
                if ((int) ($line['cancel_qty'] ?? 0) > 0) {
                    app(ShipmentService::class)->releaseItemFromPendingParcels((int) $line['order_item_id']);
                }
            }
            $order->load('items');

            $itemsById = $order->items->keyBy('id');
            $changed = 0;
            $refundLines = [];

            foreach ($lines as $line) {
                $itemId = (int)($line['order_item_id'] ?? 0);
                $cancelQty = (int)($line['cancel_qty'] ?? 0);
                $restock = (bool)($line['restock'] ?? true);

                $orderItem = $itemsById->get($itemId);
                if (!$orderItem || $cancelQty <= 0) {
                    continue;
                }

                if ((string)$orderItem->status === (string)OrderItemStatusEnum::CANCELLED()) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => __('labels.item_is_already_cancelled'),
                        'data' => ['order_item_id' => $itemId],
                    ];
                }


                $cancellation = app(FulfillmentRollupService::class)->cancellationForItem($orderItem);
                if (!$cancellation['allowed'] || $cancelQty > $cancellation['quantity']) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => __('labels.order_item_cannot_be_cancelled_at_current_status'),
                        'data' => ['order_item_id' => $itemId],
                    ];
                }

                $orderedQty = (int)$orderItem->quantity;
                $cancelQty = min($cancelQty, $orderedQty);
                $perUnitSub = $orderedQty > 0 ? ((float)$orderItem->subtotal) / $orderedQty : 0.0;
                $perUnitTax = $orderedQty > 0 ? ((float)$orderItem->tax_amount) / $orderedQty : 0.0;
                $perUnitPromo = $orderedQty > 0 ? ((float)$orderItem->promo_discount) / $orderedQty : 0.0;

                if ($cancelQty >= $orderedQty) {
                    $orderItem->status = OrderItemStatusEnum::CANCELLED();
                    $orderItem->cancel_reason = CancelReasonEnum::STAFF();
                    $orderItem->cancellation_reason = $reason;
                } else {
                    $newQty = $orderedQty - $cancelQty;
                    $orderItem->quantity = $newQty;
                    $orderItem->subtotal = round($perUnitSub * $newQty, 2);
                    $orderItem->tax_amount = round($perUnitTax * $newQty, 2);
                    $orderItem->promo_discount = round($perUnitPromo * $newQty, 2);
                }
                if ($restock && !$orderItem->is_restocked) {
                    $orderItem->is_restocked = $cancelQty >= $orderedQty;
                    $restockResult = app(OrderInventoryService::class)->restock($orderItem, $cancelQty, "Restocked {$cancelQty} unit(s) from admin cancel of Order #{$order->id} (item #{$orderItem->id}).");
                    if (($restockResult['success'] ?? false) === false) {
                        throw new Exception($restockResult['message'] ?? __('labels.something_went_wrong'));
                    }
                    app(AddonStockService::class)->restoreForOrderItem($orderItem, $cancelQty, 'admin_cancel');
                }
                $orderItem->saveQuietly();

                $refundLines[] = [
                    'order_item_id' => (int)$orderItem->id,
                    'quantity' => $cancelQty,
                    'amount' => round(($perUnitSub - $perUnitPromo) * $cancelQty, 2),
                ];

                $changed++;
            }

            if ($changed === 0) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.nothing_to_cancel'), 'data' => []];
            }

            $this->recomputeOrderMoneyFromItems($order);

            $this->updateOrderStatusAfterCancellation($order->fresh());

            $totalRefundAmount = array_sum(array_column($refundLines, 'amount'));
            if ($totalRefundAmount > 0 && $this->orderHasCollectedMoney($order)) {
                $refundRecord = $this->recordAdminCancelRefund(
                    order: $order->fresh(),
                    refundLines: $refundLines,
                    totalAmount: $totalRefundAmount,
                    reason: $reason,
                );
            }

            $this->writeAuditLog(
                orderId: $order->id,
                orderItemId: null,
                adminId: $adminId,
                action: 'admin_cancel_lines',
                oldValue: [],
                newValue: [
                    'lines' => $lines,
                    'refund_id' => $refundRecord?->id ?? "",
                    'refund_amt' => $totalRefundAmount,
                ],
                reason: $reason,
            );

            $this->refundOrderExtrasIfFullyCancelledByAdmin($order->fresh(), $adminId, $reason);

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.order_cancelled_successfully'),
                'data' => ['order' => $order->fresh()],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Admin cancel-lines failed', [
                'order_id' => $orderId, 'admin_id' => $adminId, 'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }
    }

    /**
     * Recompute order + seller-order monetary totals from the surviving
     * (non-cancelled) order items. Safe for partial-qty reductions.
     */
    private function recomputeOrderMoneyFromItems(Order $order): void
    {
        $order->load('items', 'sellerOrders');

        $active = $order->items->reject(
            fn($i) => (string)$i->status === (string)OrderItemStatusEnum::CANCELLED()
        );

        $newSubtotal = (float)$active->sum('subtotal');
        $newTax = (float)$active->sum('tax_amount');
        $newPromo = (float)$active->sum('promo_discount');

        $delivery = (float)($order->delivery_charge ?? 0);
        $giftCard = (float)($order->gift_card_discount ?? 0);
        $platformFee = (float)($order->platform_fee ?? 0);
        $codFee = (float)($order->cod_fee ?? 0);
        $dropOff = (float)($order->per_store_drop_off_fee ?? 0);

        $newFinal = max(0, $newSubtotal + $delivery + $dropOff + $platformFee + $codFee
            - ($newPromo + $giftCard));
        $newPayable = max(0, $newFinal - (float)$order->wallet_balance);

        $order->update([
            'subtotal' => $newSubtotal,
            'tax_total' => $newTax,
            'promo_discount' => $newPromo,
            'final_total' => $newFinal,
            'total_payable' => $newPayable,
        ]);

        if (!empty($order->promo_code)) {
            OrderPromoLine::where('order_id', $order->id)->update(['discount_amount' => $newPromo]);
        }

        if ($order->sellerOrders->isNotEmpty()) {
            $activeById = $active->keyBy('id');
            $itemToSeller = SellerOrderItem::whereIn('seller_order_id', $order->sellerOrders->pluck('id'))
                ->pluck('seller_order_id', 'order_item_id');

            foreach ($order->sellerOrders as $sellerOrder) {
                $sum = 0.0;
                foreach ($itemToSeller as $orderItemId => $sellerOrderId) {
                    if ((int)$sellerOrderId !== (int)$sellerOrder->id) {
                        continue;
                    }
                    $item = $activeById->get($orderItemId);
                    if ($item) {
                        $sum += (float)$item->subtotal;
                    }
                }
                $sellerOrder->update(['total_price' => $sum]);
            }
        }
    }

    /**
     * Mark an order's payment as received. Used when payment was made but not
     * reflected in the system.
     *
     * Routes through the same capture used by the gateway webhooks, so an
     * admin-marked payment commits the stock reservation, clears the cart and
     * consumes the promo exactly like a captured online payment. Setting the
     * status by hand skipped all three and left the reservation hanging.
     *
     * @param int $orderId
     * @param int $adminId
     * @param string|null $remark
     * @return array{success: bool, message: string, data: array}
     */
    public function markPaymentReceivedByAdmin(int $orderId, int $adminId, ?string $remark = null): array
    {
        try {
            DB::beginTransaction();

            $order = Order::with('items')->find($orderId);
            $oldPaymentStatus = $order->payment_status;
            if (!$order || $oldPaymentStatus != PaymentStatusEnum::PENDING()) {
                DB::rollBack();
                return ['success' => false, 'message' => (!$order === true) ? __('labels.order_not_found') : __('labels.failed_to_update_payment_status'), 'data' => []];
            }

            if (!Order::capturePayment($order->id)) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.failed_to_update_payment_status'), 'data' => []];
            }
            OrderItem::capturePayment($order->id);
            $order->refresh();

            $adminMessage = __('labels.payment_updated_by_admin');
            $pendingTransactions = OrderPaymentTransaction::where('order_id', $order->id)
                ->where('payment_status', PaymentStatusEnum::PENDING())
                ->orderBy('id')
                ->get();

            $alreadyCompleted = OrderPaymentTransaction::where('order_id', $order->id)
                ->where('payment_status', PaymentStatusEnum::COMPLETED())
                ->exists();

            $firstPending = $alreadyCompleted ? null : $pendingTransactions->shift();

            if ($firstPending) {
                $firstPending->update([
                    'payment_status' => (string)PaymentStatusEnum::COMPLETED(),
                    'amount' => (float)($order->total_payable ?? $order->final_total),
                    'currency' => $order->currency_code,
                    'message' => $adminMessage,
                ]);
            }

            foreach ($pendingTransactions as $stalePending) {
                $stalePending->update([
                    'payment_status' => (string)PaymentStatusEnum::VOIDED(),
                    'message' => $adminMessage,
                ]);
            }

            if (!$firstPending && !$alreadyCompleted) {
                OrderPaymentTransaction::create([
                    'uuid' => (string)Str::uuid(),
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'transaction_id' => 'admin_mark_' . $order->id . '_' . now()->timestamp,
                    'amount' => (float)($order->total_payable ?? $order->final_total),
                    'currency' => $order->currency_code,
                    'payment_method' => (string)$order->payment_method,
                    'payment_status' => (string)PaymentStatusEnum::COMPLETED(),
                    'message' => $adminMessage,
                    'payment_details' => [
                        'source' => 'admin_mark_payment_received',
                        'admin_id' => $adminId,
                        'remark' => $remark,
                    ],
                ]);
            }

            $autoRemark = __('labels.admin_mark_payment_received_remark');

            $this->writeAuditLog(
                orderId: $order->id,
                orderItemId: null,
                adminId: $adminId,
                action: 'admin_mark_payment_received',
                oldValue: ['payment_status' => $oldPaymentStatus],
                newValue: [
                    'payment_status' => PaymentStatusEnum::COMPLETED(),
                ],
                reason: $remark ?: $autoRemark,
            );

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.payment_marked_received_successfully'),
                'data' => [
                    'order' => $order->fresh(),
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Admin mark-payment-received failed', [
                'order_id' => $orderId,
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }
    }

    /**
     * Add a free-text admin note against an order. Lives in the audit log so
     * the timeline stays unified.
     */
    public function addOrderNoteByAdmin(int $orderId, int $adminId, string $note): array
    {
        try {
            $this->writeAuditLog(
                orderId: $orderId,
                orderItemId: null,
                adminId: $adminId,
                action: 'add_note',
                oldValue: null,
                newValue: ['note' => $note],
                reason: $note,
            );

            return [
                'success' => true,
                'message' => __('labels.note_added_successfully'),
                'data' => ['order_id' => $orderId],
            ];
        } catch (Exception $e) {
            Log::error('Admin add-note failed', [
                'order_id' => $orderId, 'admin_id' => $adminId, 'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }
    }


    /**
     * Phase 3 policy — when an admin force-cancel finalises an order (every
     * item terminal), the customer gets their delivery/handling/per-store
     * pickup fees refunded too, on top of the per-item subtotals refunded
     * during each cancel. Customer-initiated cancellations do NOT refund
     * extras (current behavior, kept).
     *
     * Idempotent: the wallet's transaction_reference dedup means a re-run
     * never double-credits.
     */
    protected function refundOrderExtrasIfFullyCancelledByAdmin(?Order $order, int $adminId, string $reason): void
    {
        if (!$order) {
            return;
        }

        if ($order->payment_status !== PaymentStatusEnum::COMPLETED()) {
            return;
        }

        $statuses = OrderItem::where('order_id', $order->id)->pluck('status')->toArray();
        if (empty($statuses)) {
            return;
        }
        $allTerminal = collect($statuses)->every(fn($s) => in_array($s, [
            OrderItemStatusEnum::CANCELLED(),
            OrderItemStatusEnum::REFUNDED(),
        ], true));
        if (!$allTerminal) {
            return;
        }

        $extras = (float)($order->delivery_charge ?? 0)
            + (float)($order->per_store_drop_off_fee ?? 0);

        if ($extras <= 0) {
            return;
        }

        $reference = 'admin_extras_refund_order_' . $order->id;

        if (OrderRefund::where('order_id', $order->id)->where('notes', $reference)->exists()) {
            return;
        }

        $walletResult = $this->walletService->addBalance($order->user_id, [
            'amount' => $extras,
            'payment_method' => 'refund',
            'idempotency_key' => $reference,
            'transaction_reference' => $reference,
            'description' => "Admin full-cancel extras refund for Order #{$order->id}: {$reason}",
        ]);

        if (!empty($walletResult['success'])) {
            OrderRefund::create([
                'order_id' => $order->id,
                'amount' => round($extras, 2),
                'shipping_refund_amount' => round($extras, 2),
                'currency_code' => $order->currency_code,
                'method' => RefundMethodEnum::WALLET(),
                'status' => RefundStatusEnum::ISSUED(),
                'reason' => $reason,
                'notes' => $reference,
                'issued_by' => $adminId,
                'issued_at' => now(),
                'wallet_transaction_id' => optional($walletResult['data']['transaction'] ?? null)->id,
            ]);

            $this->writeAuditLog(
                orderId: $order->id,
                orderItemId: null,
                adminId: $adminId,
                action: 'force_refund',
                oldValue: null,
                newValue: ['amount' => $extras, 'extras_refund' => true],
                reason: __('labels.extras_refund_audit_reason'),
            );
        }
    }

    /**
     * Wraps refundOrderExtrasIfFullyCancelledByAdmin for the seller-confirms
     * path: when the seller's confirm-return finalises an order that was
     * admin-force-cancelled, we still owe the customer the extras refund.
     * This helper looks up the most recent admin-attributed force_cancel
     * audit row to find the originating admin id + reason, then delegates.
     */
    protected function refundOrderExtrasIfAdminInitiated(?Order $order): void
    {
        if (!$order) {
            return;
        }

        $adminEntry = \App\Models\OrderAuditLog::where('order_id', $order->id)
            ->where('action', 'force_cancel')
            ->whereNotNull('admin_id')
            ->orderByDesc('created_at')
            ->first();

        if (!$adminEntry) {
            return;
        }

        $this->refundOrderExtrasIfFullyCancelledByAdmin(
            $order,
            (int)$adminEntry->admin_id,
            (string)($adminEntry->reason ?? __('labels.extras_refund_audit_reason')),
        );
    }

    /**
     * Internal helper — write a row to the audit log. Admin id is nullable so
     * the same helper can record system-generated entries (escalation, etc.).
     */
    public function writeAuditLog(
        int     $orderId,
        ?int    $orderItemId,
        ?int    $adminId,
        string  $action,
        ?array  $oldValue,
        array   $newValue,
        ?string $reason,
    ): void
    {
        try {
            OrderAuditLog::create([
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'admin_id' => $adminId,
                'action' => $action,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit log write failed', [
                'order_id' => $orderId, 'action' => $action, 'error' => $e->getMessage(),
            ]);
        }
    }

    public function returnableShipments(OrderItem $item, bool $customer = true): array
    {
        if ((string) $item->status !== (string) OrderItemStatusEnum::ACTIVE()
            || ($customer && (! $item->return_eligible
                || $item->return_deadline === null
                || Carbon::parse($item->return_deadline)->endOfDay()->isPast()))) {
            return [];
        }

        $item->loadMissing(['shipmentItems.shipment', 'returns']);
        $returns = $item->returns->reject(fn ($return) => in_array((string) $return->return_status, [
            (string) OrderItemReturnStatusEnum::CANCELLED(),
            (string) OrderItemReturnStatusEnum::DECLINED(),
        ], true));
        if ($returns->contains(fn ($return) => $return->source_shipment_id === null)) {
            return [];
        }

        if ($item->shipmentItems->isEmpty() && (string) $item->fulfillment_status === (string) FulfillmentStatusEnum::FULFILLED()) {
            return [['id' => null, 'quantity' => (int) $item->quantity, 'delivered_at' => null]];
        }

        return $item->shipmentItems->filter(fn ($line) => $line->shipment
            && ! $line->shipment->is_return
            && (string) $line->shipment->status === (string) \App\Enums\Shipping\ShipmentStatusEnum::DELIVERED())
            ->map(function ($line) use ($returns) {
                $shipment = $line->shipment;
                $reserved = $returns->where('source_shipment_id', $shipment->id)->sum('quantity');
                return [
                    'id' => (int) $shipment->id,
                    'quantity' => max(0, (int) $line->quantity - (int) $reserved),
                    'delivered_at' => $shipment->delivered_at?->toIso8601String(),
                ];
            })->filter(fn ($shipment) => $shipment['quantity'] > 0)->values()->all();
    }

    public function maximumReturnQuantity(OrderItemReturn $return): int
    {
        $orderedQty = max(1, (int) $return->orderItem?->quantity);
        if ($return->source_shipment_id === null) {
            $return->orderItem?->loadMissing('shipmentItems.shipment');
            $shipmentItems = $return->orderItem?->shipmentItems;
            if ($shipmentItems?->isNotEmpty()) {
                $deliveredQty = $shipmentItems->filter(fn ($line) => $line->shipment
                    && ! $line->shipment->is_return
                    && (string) $line->shipment->status === (string) \App\Enums\Shipping\ShipmentStatusEnum::DELIVERED())
                    ->sum('quantity');

                return max(0, min($orderedQty, (int) $deliveredQty));
            }

            return $orderedQty;
        }

        $shipmentQty = (int) $return->orderItem?->shipmentItems()
            ->where('shipment_id', $return->source_shipment_id)->value('quantity');
        $otherQty = (int) OrderItemReturn::where('order_item_id', $return->order_item_id)
            ->where('source_shipment_id', $return->source_shipment_id)
            ->whereKeyNot($return->id)
            ->whereNotIn('return_status', [
                (string) OrderItemReturnStatusEnum::CANCELLED(),
                (string) OrderItemReturnStatusEnum::DECLINED(),
            ])->sum('quantity');

        return max(0, min($orderedQty, $shipmentQty - $otherQty));
    }

    public
    function returnOrderItem(User $user, array $data): array
    {
        try {
            return DB::transaction(function () use ($user, $data) {
            $orderItem = OrderItem::with(['order', 'product', 'store'])
                ->where('id', $data['order_item_id'])
                ->whereHas('order', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->lockForUpdate()->first();

            if (!$orderItem) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('messages.order_item_not_found'),
                );
            }

            if (!$orderItem->return_eligible) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('messages.product_not_returnable'),
                );
            }

            $shipments = $this->returnableShipments($orderItem);
            if ($shipments === []) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('messages.order_item_cannot_be_returned_at_current_status'),
                );
            }

            if ($orderItem->return_deadline !== null
                && Carbon::parse($orderItem->return_deadline)->endOfDay()->isPast()) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('labels.return_deadline_expired'),
                );
            }

            $sourceShipmentId = isset($data['shipment_id']) ? (int) $data['shipment_id'] : null;
            $sourceShipment = $sourceShipmentId !== null
                ? collect($shipments)->firstWhere('id', $sourceShipmentId)
                : (count($shipments) === 1 ? $shipments[0] : null);
            if ($sourceShipment === null) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('labels.select_return_shipment'),
                );
            }

            $orderedQty = (int)$orderItem->quantity;
            $availableQty = (int) $sourceShipment['quantity'];
            $returnQty = $data['quantity'] ?? $availableQty;
            if (filter_var($returnQty, FILTER_VALIDATE_INT) === false || $returnQty < 1 || $returnQty > $availableQty) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('validation.between.numeric', ['attribute' => 'quantity', 'min' => 1, 'max' => $availableQty]),
                );
            }
            $returnQty = (int) $returnQty;

            $perUnitNet = $orderedQty > 0
                ? ((float)$orderItem->subtotal - (float)$orderItem->promo_discount) / $orderedQty
                : 0.0;
            $refundBase = round($perUnitNet * $returnQty, 2);

            $reasonCode = $data['reason_code'] ?? null;
            $reasonType = $reasonCode
                ? \App\Enums\Order\ReturnReasonCodeEnum::from($reasonCode)->faultType()
                : null;

            $return = OrderItemReturn::create([
                'order_item_id' => $orderItem->id,
                'order_id' => $orderItem->order_id,
                'user_id' => $user->id,
                'seller_id' => $orderItem->store->seller_id,
                'store_id' => $orderItem->store_id,
                'source_shipment_id' => $sourceShipment['id'],
                'quantity' => $returnQty,
                'reason' => $data['reason'] ?? null,
                'reason_code' => $reasonCode,
                'reason_type' => $reasonType,
                'refund_amount' => $refundBase,
                'return_status' => OrderItemReturnStatusEnum::REQUESTED(),
            ]);
            if (!empty($data['images'])) {
                foreach ($data['images'] as $image) {
                    SpatieMediaService::uploadFromRequest($return, $image, SpatieMediaCollectionName::ITEM_RETURN_IMAGES());
                }
            }

            app(FulfillmentRollupService::class)->recomputeOrderStatus($orderItem->order);

            return ApiResponseType::toArray(
                success: true,
                message: __('messages.return_request_sent'),
                data: new OrderItemReturnResource($return)
            );
            });
        } catch (Exception $e) {
            return ApiResponseType::toArray(
                success: false,
                message: $e->getMessage(),
                data: null
            );
        }
    }

    public
    function cancelReturnRequest(User $user, $orderItemId, ?int $returnId = null): array
    {
        try {

            $returns = OrderItemReturn::where('order_item_id', $orderItemId)
                ->where('user_id', $user->id)
                ->whereIn('return_status', [OrderItemReturnStatusEnum::REQUESTED(), OrderItemReturnStatusEnum::APPROVED()])
                ->when($returnId, fn ($query) => $query->whereKey($returnId))
                ->get();
            $return = $returns->count() === 1 ? $returns->first() : null;

            if (!$return) {
                return ApiResponseType::toArray(
                    success: false,
                    message: $returns->count() > 1
                        ? __('labels.select_return_for_cancellation')
                        : __('messages.return_request_not_found')
                );
            }

            if (!in_array($return->return_status, [OrderItemReturnStatusEnum::REQUESTED(), OrderItemReturnStatusEnum::APPROVED()])) {
                return ApiResponseType::toArray(
                    success: false,
                    message: __('messages.return_cannot_be_cancelled_now')
                );
            }

            $return->update([
                'return_status' => OrderItemReturnStatusEnum::CANCELLED(),
            ]);

            return ApiResponseType::toArray(
                success: true,
                message: __('messages.return_request_cancelled'),
                data: $return
            );

        } catch (Exception $e) {
            return ApiResponseType::toArray(
                success: false,
                message: $e->getMessage()
            );
        }
    }

    /**
     * Admin Refund screen — Shopify-parity flow for issuing a refund (full or
     * partial) against an already-paid order. Differs from
     * {@see cancelOrderLinesByAdmin} in two ways:
     *
     *   1. Fulfilled items are NOT refundable here — the customer has to
     *      return them first. Caller (controller / view) filters those out
     *      and we double-check defensively.
     *   2. The refund amount is the admin's choice, not derived. They can
     *      refund less than what the selected items are worth (goodwill cap)
     *      or more (settling a previously-owed amount). The amount can never
     *      exceed amount_paid - amount_refunded - we cap it before crediting.
     *
     * Order of operations on submit:
     *   - Mark selected lines REFUNDED (or reduce qty for partial)
     *   - Restock cancelled units when admin asked for it
     *   - Credit wallet (market→base converted)
     *   - Settle existing OWED refund rows up to the credited amount, oldest
     *     first — this lets the admin clear historical commitments through
     *     the same flow
     *   - Any leftover credit lands as a new ISSUED OrderRefund row attached
     *     to the items the admin selected
     *   - Recompute payment_status (partially_refunded / refunded)
     *
     * @param array<int,array{order_item_id:int,refund_qty:int,restock:bool}> $lines
     * @return array{success:bool,message:string,data:array}
     */
    public function refundOrderLinesByAdmin(
        int    $orderId,
        int    $adminId,
        array  $lines,
        float  $shippingRefund,
        float  $refundAmount,
        string $reason,
        bool   $notifyCustomer = true,
    ): array
    {
        try {
            DB::beginTransaction();

            $order = Order::with(['items', 'refunds'])->lockForUpdate()->find($orderId);
            if (!$order) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.order_not_found'), 'data' => []];
            }

            if ((string)$order->status === (string)OrderStatusEnum::ARCHIVED()) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.order_archived_reopen_first'), 'data' => []];
            }

            if (!$this->orderHasCollectedMoney($order)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => __('labels.refund_blocked_no_payment'),
                    'data' => [],
                ];
            }

            $amountPaid = $order->amount_paid;
            $alreadyIssued = $order->amount_refunded;
            $alreadyOwed = $order->amount_refund_owed;
            $maxRefundable = max(0, $amountPaid - $alreadyIssued);

            if ($refundAmount > $maxRefundable + 0.01) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => __('labels.refund_exceeds_available', ['amount' => number_format($maxRefundable, 2)]),
                    'data' => [],
                ];
            }

            $refundableReturnStatuses = [
                (string)OrderItemReturnStatusEnum::APPROVED(),
                (string)OrderItemReturnStatusEnum::IN_TRANSIT(),
                (string)OrderItemReturnStatusEnum::RECEIVED(),
            ];
            $approvedReturns = OrderItemReturn::where('order_id', $order->id)
                ->whereIn('return_status', $refundableReturnStatuses)
                ->lockForUpdate()->get()->groupBy('order_item_id');
            foreach ($lines as $line) {
                $itemId = (int) ($line['order_item_id'] ?? 0);
                if ($itemId && ! $approvedReturns->has($itemId)) {
                    app(ShipmentService::class)->releaseItemFromPendingParcels($itemId);
                }
            }
            $order->load('items');

            $itemsById = $order->items->keyBy('id');
            $activeShipmentItemIds = $this->itemIdsInActiveShipment(
                $order->items->pluck('id')->map(fn($id) => (int)$id)->all()
            );
            $refundLines = [];
            $refundedReturnIds = [];

            foreach ($lines as $line) {
                $itemId = (int)($line['order_item_id'] ?? 0);
                $refundQty = (int)($line['refund_qty'] ?? 0);
                $restock = (bool)($line['restock'] ?? true);

                $orderItem = $itemsById->get($itemId);
                if (!$orderItem || $refundQty <= 0) {
                    continue;
                }

                $itemReturns = $approvedReturns->get($itemId, collect());
                $hasApprovedReturn = $itemReturns->isNotEmpty();
                $selectedReturn = null;
                if ($hasApprovedReturn) {
                    $returnId = (int) ($line['return_id'] ?? 0);
                    $selectedReturn = $returnId
                        ? $itemReturns->firstWhere('id', $returnId)
                        : ($itemReturns->count() === 1 ? $itemReturns->first() : null);
                    if (! $selectedReturn || $refundQty !== (int) $selectedReturn->quantity
                        || in_array((int) $selectedReturn->id, $refundedReturnIds, true)) {
                        DB::rollBack();
                        return ['success' => false, 'message' => __('labels.select_return_for_refund'), 'data' => ['order_item_id' => $itemId]];
                    }
                } elseif (! empty($line['return_id'])) {
                    DB::rollBack();
                    return ['success' => false, 'message' => __('labels.select_return_for_refund'), 'data' => ['order_item_id' => $itemId]];
                }
                if (!$hasApprovedReturn
                    && ((string)$orderItem->fulfillment_status !== (string)FulfillmentStatusEnum::UNFULFILLED()
                        || in_array($itemId, $activeShipmentItemIds, true))) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => __('labels.refund_blocked_fulfilled_create_return'),
                        'data' => ['order_item_id' => $itemId],
                    ];
                }

                if ((string)$orderItem->status !== (string)OrderItemStatusEnum::ACTIVE()) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => __('labels.item_is_already_cancelled'),
                        'data' => ['order_item_id' => $itemId],
                    ];
                }

                $orderedQty = (int)$orderItem->quantity;
                if ($refundQty > $orderedQty) {
                    DB::rollBack();
                    return ['success' => false, 'message' => __('labels.return_quantity_exceeds_ordered'), 'data' => ['order_item_id' => $itemId]];
                }
                $refundQty = min($refundQty, $orderedQty);
                $perUnitSub = $orderedQty > 0 ? ((float)$orderItem->subtotal) / $orderedQty : 0.0;
                $perUnitTax = $orderedQty > 0 ? ((float)$orderItem->tax_amount) / $orderedQty : 0.0;
                $perUnitPromo = $orderedQty > 0 ? ((float)$orderItem->promo_discount) / $orderedQty : 0.0;

                if ($refundQty >= $orderedQty) {
                    $orderItem->status = OrderItemStatusEnum::REFUNDED();
                    $orderItem->cancellation_reason = $reason ?: __('labels.refund_default_reason');
                } else {
                    $newQty = $orderedQty - $refundQty;
                    $orderItem->quantity = $newQty;
                    $orderItem->subtotal = round($perUnitSub * $newQty, 2);
                    $orderItem->tax_amount = round($perUnitTax * $newQty, 2);
                    $orderItem->promo_discount = round($perUnitPromo * $newQty, 2);
                }

                if ($restock && !$orderItem->is_restocked) {
                    $orderItem->is_restocked = $refundQty >= $orderedQty;
                    $restockResult = app(OrderInventoryService::class)->restock($orderItem, $refundQty, "Restocked {$refundQty} unit(s) from admin refund of Order #{$order->id} (item #{$orderItem->id}).");
                    if (($restockResult['success'] ?? false) === false) {
                        throw new Exception($restockResult['message'] ?? __('labels.something_went_wrong'));
                    }
                    app(AddonStockService::class)->restoreForOrderItem($orderItem, $refundQty, 'admin_refund');
                }
                $orderItem->saveQuietly();

                $refundLines[] = [
                    'order_item_id' => (int)$orderItem->id,
                    'quantity' => $refundQty,
                    'amount' => round(($perUnitSub - $perUnitPromo + $perUnitTax) * $refundQty, 2),
                ];
                if ($selectedReturn) {
                    $refundedReturnIds[] = (int) $selectedReturn->id;
                }
            }

            if ($refundedReturnIds) {
                OrderItemReturn::whereKey($refundedReturnIds)
                    ->update([
                        'return_status' => (string)OrderItemReturnStatusEnum::REFUNDED(),
                        'refund_processed_at' => now(),
                    ]);
            }

            $rate = (float)($order->currency_rate ?: 1);
            $walletAmountBase = $rate > 0 ? round($refundAmount / $rate, 2) : $refundAmount;
            $walletResult = $this->walletService->addBalance($order->user_id, [
                'amount' => $walletAmountBase,
                'payment_method' => 'refund',
                'idempotency_key' => "admin_refund_order_{$order->id}_" . Str::uuid(),
                'description' => "Refund issued for Order #{$order->id}.",
            ]);

            if (!($walletResult['success'] ?? false)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => $walletResult['message'] ?? __('messages.refund_processing_failed'),
                    'data' => [],
                ];
            }
            $walletTxId = optional($walletResult['data']['transaction'] ?? null)->id;

            $newRefund = OrderRefund::create([
                'order_id' => $order->id,
                'amount' => round($refundAmount, 2),
                'shipping_refund_amount' => round((float)$shippingRefund, 2),
                'currency_code' => $order->currency_code,
                'method' => RefundMethodEnum::WALLET(),
                'status' => RefundStatusEnum::ISSUED(),
                'reason' => $reason,
                'issued_by' => $adminId,
                'issued_at' => now(),
                'wallet_transaction_id' => $walletTxId,
            ]);

            if (!empty($refundLines)) {
                $pivot = [];
                foreach ($refundLines as $rl) {
                    $pivot[$rl['order_item_id']] = [
                        'quantity' => $rl['quantity'],
                        'amount' => $rl['amount'],
                    ];
                }
                $newRefund->items()->attach($pivot);
            }

            if ($alreadyOwed > 0) {
                $lineItemsTotal = array_sum(array_map(
                    fn($l) => (float)($l['amount'] ?? 0),
                    $refundLines
                ));
                $remaining = max(0, round($refundAmount - $lineItemsTotal - (float)$shippingRefund, 2));
                $owedRefunds = $order->refunds()
                    ->where('status', RefundStatusEnum::OWED())
                    ->whereNull('settled_by_refund_id')
                    ->orderBy('created_at')
                    ->get();
                foreach ($owedRefunds as $owed) {
                    if ($remaining <= 0.01) break;
                    $rowAmount = (float)$owed->amount;
                    if (round($remaining, 2) >= round($rowAmount, 2)) {
                        $owed->update(['settled_by_refund_id' => $newRefund->id]);
                        $remaining = round($remaining - $rowAmount, 2);
                    } else {
                        $settledPortion = round($remaining, 2);
                        $leftoverPortion = round($rowAmount - $settledPortion, 2);
                        $originalItems = $owed->items()->get();

                        $owed->update([
                            'amount' => $settledPortion,
                            'settled_by_refund_id' => $newRefund->id,
                        ]);

                        $leftover = OrderRefund::create([
                            'order_id' => $order->id,
                            'amount' => $leftoverPortion,
                            'currency_code' => $owed->currency_code,
                            'method' => $owed->method,
                            'status' => RefundStatusEnum::OWED(),
                            'reason' => $owed->reason,
                            'notes' => __('labels.refund_split_remainder_note'),
                        ]);

                        if ($rowAmount > 0 && $originalItems->isNotEmpty()) {
                            $settledRatio = $settledPortion / $rowAmount;
                            $settledPivot = [];
                            $leftoverPivot = [];

                            foreach ($originalItems as $originalItem) {
                                $pivotAmount = (float) $originalItem->pivot->amount;
                                $settledShare = round($pivotAmount * $settledRatio, 2);

                                $settledPivot[$originalItem->id] = [
                                    'quantity' => $originalItem->pivot->quantity,
                                    'amount' => $settledShare,
                                ];
                                $leftoverPivot[$originalItem->id] = [
                                    'quantity' => $originalItem->pivot->quantity,
                                    'amount' => round($pivotAmount - $settledShare, 2),
                                ];
                            }

                            $owed->items()->sync($settledPivot);
                            $leftover->items()->sync($leftoverPivot);
                        }

                        $remaining = 0;
                    }
                }
            }

            if (!empty($refundLines)) {
                $this->recomputeOrderMoneyFromItems($order);
                $this->updateOrderStatusAfterCancellation($order->fresh());
            }

            $fresh = $order->fresh();
            $issuedRefunded = (float)$fresh->amount_refunded;
            $totalRefundedNow = $issuedRefunded + (float)$fresh->amount_refund_owed;
            if ($amountPaid > 0 && $issuedRefunded >= $amountPaid - 0.01) {
                $order->update(['payment_status' => PaymentStatusEnum::REFUNDED()]);
            } elseif ($totalRefundedNow > 0) {
                $order->update(['payment_status' => PaymentStatusEnum::PARTIALLY_REFUNDED()]);
            }

            $this->writeAuditLog(
                orderId: $order->id,
                orderItemId: null,
                adminId: $adminId,
                action: 'admin_refund_order',
                oldValue: [],
                newValue: [
                    'lines' => $lines,
                    'shipping_refund' => $shippingRefund,
                    'refund_amount' => $refundAmount,
                    'refund_id' => $newRefund?->id,
                    'notify_customer' => $notifyCustomer,
                ],
                reason: $reason ?: __('labels.refund_default_reason'),
            );

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.refund_issued_successfully'),
                'data' => [
                    'order' => $order->fresh(),
                    'refund' => $newRefund,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Admin refund failed', [
                'order_id' => $orderId,
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }
    }

    /**
     * Approve a return (admin) — Shopify's "Approve return". Records the return
     * shipping charge + carrier/tracking/label and (optionally) an updated qty,
     * and sets the status to APPROVED. NO money moves here — the refund is issued
     * later from the order refund page. Re-running it on an already-approved
     * return acts as an edit.
     */
    public function approveReturnByAdmin(int $returnId, int $adminId, array $data = []): array
    {
        try {
            $return = OrderItemReturn::with('orderItem')->find($returnId);
            if (!$return || !$return->orderItem) {
                return ['success' => false, 'message' => __('labels.return_request_not_found'), 'data' => []];
            }

            $finalStatuses = [
                (string)OrderItemReturnStatusEnum::REFUNDED(),
                (string)OrderItemReturnStatusEnum::DECLINED(),
                (string)OrderItemReturnStatusEnum::CANCELLED(),
                (string)OrderItemReturnStatusEnum::CLOSED(),
                (string)OrderItemReturnStatusEnum::APPROVED(),
                (string)OrderItemReturnStatusEnum::IN_TRANSIT(),
                (string)OrderItemReturnStatusEnum::RECEIVED(),
            ];
            if (in_array((string)$return->return_status, $finalStatuses, true)) {
                return ['success' => false, 'message' => __('labels.return_already_finalised'), 'data' => []];
            }

            $orderedQty = $this->maximumReturnQuantity($return);
            $qty = isset($data['quantity'])
                ? (int) $data['quantity']
                : (int) $return->quantity;
            if ($qty < 1 || $qty > $orderedQty) {
                return ['success' => false, 'message' => __('validation.between.numeric', ['attribute' => 'quantity', 'min' => 1, 'max' => $orderedQty]), 'data' => []];
            }

            $payer = ((string)($return->reason_type->value ?? '') === (string)\App\Enums\Order\ReturnReasonTypeEnum::CUSTOMER_FAULT())
                ? 'CUSTOMER' : 'SELLER';

            $claimed = OrderItemReturn::whereKey($return->id)
                ->whereNotIn('return_status', $finalStatuses)
                ->update(['return_status' => OrderItemReturnStatusEnum::APPROVED()]);

            if ($claimed === 0) {
                return ['success' => false, 'message' => __('labels.return_already_finalised'), 'data' => []];
            }

            $return->update([
                'quantity' => $qty,
                'return_status' => OrderItemReturnStatusEnum::APPROVED(),
                'seller_approved_at' => $return->seller_approved_at ?: now(),
                'return_shipping_charge' => array_key_exists('return_shipping_charge', $data)
                    ? max(0, round((float)$data['return_shipping_charge'], 2))
                    : (float)$return->return_shipping_charge,
                'shipping_cost_payer' => $payer,
                'return_carrier' => array_key_exists('return_carrier', $data) ? ($data['return_carrier'] ?: null) : $return->return_carrier,
                'return_tracking_number' => array_key_exists('return_tracking_number', $data) ? ($data['return_tracking_number'] ?: null) : $return->return_tracking_number,
                'return_tracking_url' => array_key_exists('return_tracking_url', $data) ? ($data['return_tracking_url'] ?: null) : $return->return_tracking_url,
                'return_label_url' => array_key_exists('return_label_url', $data) ? ($data['return_label_url'] ?: null) : $return->return_label_url,
                'no_shipping_required' => array_key_exists('no_shipping_required', $data) ? (bool)$data['no_shipping_required'] : (bool)$return->no_shipping_required,
                'reason' => array_key_exists('reason', $data) ? ($data['reason'] ?: $return->reason) : $return->reason,
            ]);

            $pickupShipment = app(ShipmentService::class)->requestCarrierReturnPickup($return->fresh());
            $pickupBookingFailed = $pickupShipment === null;

            $this->writeAuditLog(
                orderId: $return->order_id,
                orderItemId: $return->order_item_id,
                adminId: $adminId,
                action: 'admin_return_approved',
                oldValue: [],
                newValue: [
                    'return_id' => $return->id,
                    'quantity' => $qty,
                    'return_shipping_charge' => (float)$return->return_shipping_charge,
                ],
                reason: $data['reason'] ?? __('labels.return_default_reason'),
            );

            if ($order = Order::find($return->order_id)) {
                app(FulfillmentRollupService::class)->recomputeOrderStatus($order);
            }

            return [
                'success' => true,
                'message' => __('labels.return_approved'),
                'data' => [
                    'return' => $return->fresh(),
                    'pickup_booking_failed' => $pickupBookingFailed,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Admin approve return failed', ['return_id' => $returnId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }
    }

    /**
     * Cancel a return (admin) — frees the item to be returned again. No refund.
     */
    public function cancelReturnByAdmin(int $returnId, int $adminId, ?string $reason = null): array
    {
        try {
            $return = OrderItemReturn::find($returnId);
            if (!$return) {
                return ['success' => false, 'message' => __('labels.return_request_not_found'), 'data' => []];
            }
            $finalStatuses = [
                (string)OrderItemReturnStatusEnum::REFUNDED(),
                (string)OrderItemReturnStatusEnum::DECLINED(),
                (string)OrderItemReturnStatusEnum::CANCELLED(),
                (string)OrderItemReturnStatusEnum::CLOSED(),
            ];
            if (in_array((string)$return->return_status, $finalStatuses, true)) {
                return ['success' => false, 'message' => __('labels.return_already_finalised'), 'data' => []];
            }

            $return->update([
                'return_status' => OrderItemReturnStatusEnum::CANCELLED(),
                'seller_comment' => $reason ?: $return->seller_comment,
            ]);

            $this->writeAuditLog(
                orderId: $return->order_id,
                orderItemId: $return->order_item_id,
                adminId: $adminId,
                action: 'admin_return_cancelled',
                oldValue: [],
                newValue: ['return_id' => $return->id, 'reason' => $reason],
                reason: $reason ?: __('labels.return_default_reason'),
            );

            if ($order = Order::find($return->order_id)) {
                app(FulfillmentRollupService::class)->recomputeOrderStatus($order);
            }

            return ['success' => true, 'message' => __('labels.return_cancelled'), 'data' => ['return' => $return->fresh()]];
        } catch (Exception $e) {
            Log::error('Admin cancel return failed', ['return_id' => $returnId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }
    }

    /**
     * Create a return directly from the admin panel (no customer request). The
     * whole item is returned; the admin then processes the refund (qty +
     * deductions) on the return-refund screen.
     */
    public function createDirectReturnByAdmin(int $orderItemId, int $adminId, ?string $reasonCode, ?string $note, ?int $quantity = null, ?int $sourceShipmentId = null): array
    {
        try {
            return DB::transaction(function () use ($orderItemId, $adminId, $reasonCode, $note, $quantity, $sourceShipmentId) {
            $orderItem = OrderItem::with(['order', 'store'])->lockForUpdate()->find($orderItemId);
            if (!$orderItem || !$orderItem->order) {
                return ['success' => false, 'message' => __('labels.order_item_not_found'), 'data' => []];
            }
            if ((string)$orderItem->order->status === (string)OrderStatusEnum::ARCHIVED()) {
                return ['success' => false, 'message' => __('labels.order_archived_reopen_first'), 'data' => []];
            }
            if ((string)$orderItem->status !== (string)OrderItemStatusEnum::ACTIVE()) {
                return ['success' => false, 'message' => __('labels.item_is_already_cancelled'), 'data' => []];
            }
            $shipments = $this->returnableShipments($orderItem, false);
            if ($shipments === []) {
                return ['success' => false, 'message' => __('labels.return_requires_delivered'), 'data' => []];
            }
            $sourceShipment = $sourceShipmentId !== null
                ? collect($shipments)->firstWhere('id', $sourceShipmentId)
                : (count($shipments) === 1 ? $shipments[0] : null);
            if ($sourceShipment === null) {
                return ['success' => false, 'message' => __('labels.select_return_shipment'), 'data' => []];
            }

            $orderedQty = (int)$orderItem->quantity;
            $availableQty = (int) $sourceShipment['quantity'];
            $returnQty = $quantity ?? $availableQty;
            if ($returnQty < 1 || $returnQty > $availableQty) {
                return ['success' => false, 'message' => __('validation.between.numeric', ['attribute' => 'quantity', 'min' => 1, 'max' => $availableQty]), 'data' => []];
            }
            $perUnitNet = $orderedQty > 0
                ? ((float)$orderItem->subtotal - (float)$orderItem->promo_discount) / $orderedQty
                : 0.0;
            $refundBase = round($perUnitNet * $returnQty, 2);
            $reasonType = $reasonCode
                ? \App\Enums\Order\ReturnReasonCodeEnum::from($reasonCode)->faultType()
                : null;

            $return = OrderItemReturn::create([
                'order_item_id' => $orderItem->id,
                'order_id' => $orderItem->order_id,
                'user_id' => $orderItem->order->user_id,
                'seller_id' => $orderItem->store->seller_id ?? null,
                'store_id' => $orderItem->store_id,
                'source_shipment_id' => $sourceShipment['id'],
                'quantity' => $returnQty,
                'reason' => $note,
                'reason_code' => $reasonCode,
                'reason_type' => $reasonType,
                'refund_amount' => $refundBase,
                'return_status' => OrderItemReturnStatusEnum::REQUESTED(),
            ]);

            app(FulfillmentRollupService::class)->recomputeOrderStatus($orderItem->order);

            return ['success' => true, 'message' => __('labels.return_created'), 'data' => ['return_id' => $return->id]];
            });
        } catch (Exception $e) {
            Log::error('Direct admin return failed', ['order_item_id' => $orderItemId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }
    }

    /**
     * Decline a return (admin) — no refund is issued, the return is closed as
     * DECLINED.
     */
    public function declineReturnByAdmin(int $returnId, int $adminId, ?string $reason = null): array
    {
        try {
            $return = OrderItemReturn::find($returnId);
            if (!$return) {
                return ['success' => false, 'message' => __('labels.return_request_not_found'), 'data' => []];
            }
            $finalStatuses = [
                (string)OrderItemReturnStatusEnum::REFUNDED(),
                (string)OrderItemReturnStatusEnum::DECLINED(),
                (string)OrderItemReturnStatusEnum::CANCELLED(),
                (string)OrderItemReturnStatusEnum::CLOSED(),
                (string)OrderItemReturnStatusEnum::APPROVED(),
                (string)OrderItemReturnStatusEnum::IN_TRANSIT(),
                (string)OrderItemReturnStatusEnum::RECEIVED(),
            ];
            if (in_array((string)$return->return_status, $finalStatuses, true)) {
                return ['success' => false, 'message' => __('labels.return_already_finalised'), 'data' => []];
            }

            $claimed = OrderItemReturn::whereKey($return->id)
                ->whereNotIn('return_status', $finalStatuses)
                ->update(['return_status' => OrderItemReturnStatusEnum::DECLINED()]);

            if ($claimed === 0) {
                return ['success' => false, 'message' => __('labels.return_already_finalised'), 'data' => []];
            }

            $return->update([
                'return_status' => OrderItemReturnStatusEnum::DECLINED(),
                'seller_comment' => $reason ?: $return->seller_comment,
            ]);

            $this->writeAuditLog(
                orderId: $return->order_id,
                orderItemId: $return->order_item_id,
                adminId: $adminId,
                action: 'admin_return_declined',
                oldValue: [],
                newValue: ['return_id' => $return->id, 'reason' => $reason],
                reason: $reason ?: __('labels.return_default_reason'),
            );

            if ($order = Order::find($return->order_id)) {
                app(FulfillmentRollupService::class)->recomputeOrderStatus($order);
            }

            return ['success' => true, 'message' => __('labels.return_declined'), 'data' => ['return' => $return->fresh()]];
        } catch (Exception $e) {
            Log::error('Admin decline return failed', ['return_id' => $returnId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }
    }

    /**
     * Reopen an archived order so the admin can make changes (return/refund). The
     * order's PENDING settlement is reversed first; if any seller statement has
     * already been settled into a wallet, the reopen is refused so the admin can
     * handle the wallet adjustment deliberately. A fresh grace window starts.
     */
    public function reopenArchivedOrder(int $orderId, int $adminId): array
    {
        try {
            DB::beginTransaction();

            $order = Order::find($orderId);
            if (!$order) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.order_not_found'), 'data' => []];
            }
            if ((string)$order->status !== (string)OrderStatusEnum::ARCHIVED()) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.order_not_archived'), 'data' => []];
            }

            $reversal = app(OrderSettlementService::class)->reverseSettlement($order);
            if (!$reversal['success']) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => __('labels.reopen_blocked_settled'),
                    'data' => ['settled' => $reversal['settled']],
                ];
            }

            $order->update([
                'status' => OrderStatusEnum::OPEN(),
                'resolved_at' => now(),
            ]);

            $this->writeAuditLog(
                orderId: $order->id,
                orderItemId: null,
                adminId: $adminId,
                action: 'admin_order_reopened',
                oldValue: ['status' => (string)OrderStatusEnum::ARCHIVED()],
                newValue: ['status' => (string)OrderStatusEnum::OPEN(), 'settlements_reversed' => $reversal['reversed']],
                reason: null,
            );

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.order_reopened'),
                'data' => ['settlements_reversed' => $reversal['reversed']],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Reopen archived order failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }
    }

    /**
     * Restock selected order items (admin) — add their units back to inventory
     * and flag them restocked. Only cancelled / returned / refunded lines that
     * haven't already been restocked are eligible.
     */
    public function restockOrderItemsByAdmin(int $orderId, int $adminId, array $itemIds): array
    {
        try {
            $items = OrderItem::with('sellerOrderItem')->whereIn('id', $itemIds)->get();
            if (!$items) {
                return ['success' => false, 'message' => __('labels.order_not_found'), 'data' => []];
            }

            $restockable = [
                (string)OrderItemStatusEnum::CANCELLED(),
                (string)OrderItemStatusEnum::RETURNED(),
                (string)OrderItemStatusEnum::REFUNDED(),
            ];

            DB::beginTransaction();
            $done = [];
            $failed = [];
            foreach ($items as $item) {
                $returnedQty = 0;

                if (!in_array((string)$item->status, $restockable, true)) {
                    $returnedQty = (int) OrderItemReturn::where('order_item_id', $item->id)
                        ->whereIn('return_status', [
                            (string)OrderItemReturnStatusEnum::RECEIVED(),
                            (string)OrderItemReturnStatusEnum::REFUNDED(),
                        ])
                        ->sum('quantity');

                    if ($returnedQty <= 0) {
                        continue;
                    }
                }

                $claimedItem = OrderItem::whereKey($item->id)
                    ->where(function ($q) {
                        $q->whereNull('is_restocked')->orWhere('is_restocked', false);
                    })
                    ->update(['is_restocked' => true]);

                if ($claimedItem === 0) {
                    continue;
                }

                $qty = $returnedQty > 0 ? $returnedQty : (int)$item->quantity;
                if ($qty <= 0 || !$item->product_variant_id || !$item->store_id) {
                    OrderItem::whereKey($item->id)->update(['is_restocked' => false]);
                    continue;
                }

                $locationId = $item->sellerOrderItem?->sellerOrder?->store_location_id;
                $restockResult = app(OrderInventoryService::class)->restock(
                    $item,
                    $qty,
                    "Restocked {$qty} unit(s) from Order #{$item->order_id} (item #{$item->id}) by admin.",
                );

                if (($restockResult['success'] ?? false) === false) {
                    Log::error('Admin restock failed', [
                        'order_item_id' => $item->id,
                        'message' => $restockResult['message'] ?? null,
                    ]);
                    OrderItem::whereKey($item->id)->update(['is_restocked' => false]);
                    $failed[] = (int)$item->id;
                    continue;
                }

                app(AddonStockService::class)->restoreForOrderItem($item, $qty, 'admin_restock');

                $item->is_restocked = true;
                $item->saveQuietly();
                $done[] = (int)$item->id;
                $this->writeAuditLog(
                    orderId: $item->order_id,
                    orderItemId: null,
                    adminId: $adminId,
                    action: 'admin_restock',
                    oldValue: [],
                    newValue: ['item_ids' => $item->id],
                    reason: __('labels.items_restocked'),
                );

            }

            if (empty($done)) {
                DB::rollBack();
                return ['success' => false, 'message' => __('labels.no_items_restocked'), 'data' => []];
            }
            DB::commit();
            return [
                'success' => true,
                'message' => __('labels.items_restocked'),
                'data' => ['item_ids' => $done, 'failed_item_ids' => $failed],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Admin restock failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => __('labels.something_went_wrong'), 'data' => []];
        }
    }

    private function lockCancellationItems(array $orderItemIds): void
    {
        $sellerOrderIds = SellerOrderItem::whereIn('order_item_id', $orderItemIds)->pluck('seller_order_id');
        SellerOrder::whereIn('id', $sellerOrderIds)->orderBy('id')->lockForUpdate()->get();
        OrderItem::whereIn('id', $orderItemIds)->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * Did the customer actually pay us anything for this order? Mirrors the
     * "should we refund?" gate from {@see processOrderItemRefund}: COD orders
     * haven't paid until delivery, prepaid orders have paid once payment_status
     * reaches COMPLETED / PAID.
     */
    /**
     * Order-item ids that are currently inside an ACTIVE (non-terminal) shipment.
     *
     * `fulfillment_status` alone is not enough to gate cancel/refund: it only
     * leaves UNFULFILLED once a shipment is DELIVERED, so an item that has been
     * handed to a carrier (CONFIRMED / IN_TRANSIT / OUT_FOR_DELIVERY) is still
     * UNFULFILLED yet must NOT be cancellable/refundable — it has physically
     * shipped and belongs in the return flow. "Active" mirrors ShipmentService:
     * any shipment whose status is not terminal (DELIVERED/RETURNED/CANCELLED/
     * RTO_DELIVERED/LOST). Cancelled shipments therefore free the item again.
     *
     * @param array<int,int> $orderItemIds
     * @return array<int,int>
     */
    public function itemIdsInActiveShipment(array $orderItemIds): array
    {
        if (empty($orderItemIds)) {
            return [];
        }

        $terminal = array_map(fn($s) => (string)$s, ShipmentStatusEnum::terminal());

        return ShipmentItem::query()
            ->whereIn('order_item_id', $orderItemIds)
            ->whereHas('shipment', fn($q) => $q->whereNotIn('status', $terminal)->where('is_return', false))
            ->pluck('order_item_id')
            ->map(fn($id) => (int)$id)
            ->unique()
            ->values()
            ->all();
    }

    protected function orderHasCollectedMoney(Order $order): bool
    {
        return in_array((string)$order->payment_status, [
            (string)PaymentStatusEnum::COMPLETED(),
            (string)PaymentStatusEnum::PARTIALLY_REFUNDED(),
            (string)PaymentStatusEnum::REFUNDED(),
        ], true);
    }

    /**
     * Persist an OrderRefund (+ items pivot) for an admin cancel, credit the
     * customer's wallet, and roll the order's payment_status forward so the
     * cancel page math stays correct on the next visit.
     *
     * Today the only method is WALLET (instant credit) → record lands as
     * ISSUED. When gateway refunds get wired up, this is the function to
     * branch on `$method` — gateway refunds will start life as OWED and flip
     * to ISSUED when the gateway callback confirms.
     *
     * @param array<int,array{order_item_id:int,quantity:int,amount:float}> $refundLines
     */
    protected function recordAdminCancelRefund(
        Order  $order,
        array  $refundLines,
        float  $totalAmount,
        string $reason,
    ): ?OrderRefund
    {

        $refund = OrderRefund::create([
            'order_id' => $order->id,
            'amount' => $totalAmount,
            'currency_code' => $order->currency_code,
            'method' => RefundMethodEnum::WALLET(),
            'status' => RefundStatusEnum::OWED(),
            'reason' => $reason,
            'issued_by' => null,
            'issued_at' => null,
            'wallet_transaction_id' => null,
        ]);

        $pivot = [];
        foreach ($refundLines as $line) {
            $pivot[$line['order_item_id']] = [
                'quantity' => $line['quantity'],
                'amount' => $line['amount'],
            ];
        }
        if (!empty($pivot)) {
            $refund->items()->attach($pivot);
        }

        return $refund;
    }

    /**
     * Process refund for cancelled order item
     *
     * @param OrderItem $orderItem The cancelled order item
     * @return array Result containing success status and refund details
     */
    public
    function processOrderItemRefund(OrderItem $orderItem, $type = 'cancel', ?float $overrideAmount = null, ?int $overrideQty = null): array
    {
        try {
            $order = $orderItem->order;
            $refundAmount = 0;

            $itemRefundAmount = $overrideAmount !== null
                ? $overrideAmount
                : ($orderItem->subtotal - $orderItem->promo_discount);

            $paymentCollected = $order->payment_status === PaymentStatusEnum::COMPLETED();
            $prepaidNonCod = $order->payment_method !== PaymentTypeEnum::COD();
            $shouldRefundToWallet = $type === 'return_pickup'
                ? $paymentCollected
                : ($prepaidNonCod && $paymentCollected);

            if ($shouldRefundToWallet) {

                $itemRefundAmount = round((float) $itemRefundAmount, 2);

                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first() ?? $order;
                $remainingRefundable = max(0, (float) $lockedOrder->amount_paid - (float) $lockedOrder->amount_refunded);
                $itemRefundAmount = min($itemRefundAmount, round($remainingRefundable, 2));

                if ($itemRefundAmount <= 0) {
                    return [
                        'success' => true,
                        'message' => __('messages.refund_processed_successfully'),
                        'data' => ['refund_amount' => 0],
                    ];
                }

                $rate = (float) ($order->currency_rate ?: 1);
                $walletAmountBase = $rate > 0 ? round($itemRefundAmount / $rate, 2) : $itemRefundAmount;

                $description = "Refund for cancelled order item #{$orderItem->id} from Order #{$order->id}";
                $walletResult = $this->walletService->addBalance($order->user_id, [
                    'amount' => $walletAmountBase,
                    'payment_method' => 'refund',
                    'idempotency_key' => "refund_order_item_{$orderItem->id}_{$type}_" . (int) round($itemRefundAmount * 100),
                    'description' => $description,
                ]);

                if (!$walletResult['success']) {
                    return [
                        'success' => false,
                        'message' => __('messages.refund_processing_failed'),
                        'data' => ['error' => $walletResult['message']]
                    ];
                }

                $walletTxId = optional($walletResult['data']['transaction'] ?? null)->id;
                $refund = OrderRefund::create([
                    'order_id'               => $order->id,
                    'amount'                 => $itemRefundAmount,
                    'shipping_refund_amount' => 0,
                    'currency_code'          => $order->currency_code,
                    'method'                 => RefundMethodEnum::WALLET(),
                    'status'                 => RefundStatusEnum::ISSUED(),
                    'reason'                 => __('messages.refund_for_cancelled_item'),
                    'issued_by'              => null,
                    'issued_at'              => now(),
                    'wallet_transaction_id'  => $walletTxId,
                ]);
                $refund->items()->attach([
                    (int) $orderItem->id => [
                        'quantity' => $overrideQty ?? (int) $orderItem->quantity,
                        'amount'   => $itemRefundAmount,
                    ],
                ]);

                $freshOrder = $order->fresh();
                $amountPaid = (float) $freshOrder->amount_paid;
                $totalRefundedNow = (float) $freshOrder->amount_refunded + (float) $freshOrder->amount_refund_owed;
                if ($amountPaid > 0 && $totalRefundedNow >= $amountPaid - 0.01) {
                    $order->update(['payment_status' => PaymentStatusEnum::REFUNDED()]);
                } elseif ($totalRefundedNow > 0) {
                    $order->update(['payment_status' => PaymentStatusEnum::PARTIALLY_REFUNDED()]);
                }

                $refundAmount = $itemRefundAmount;
            }

            return [
                'success' => true,
                'message' => __('messages.refund_processed_successfully'),
                'data' => [
                    'refund_amount' => $refundAmount,
                    'refund_method' => $refundAmount > 0 ? 'wallet' : 'none'
                ]
            ];

        } catch (Exception $e) {
            Log::error('Error processing order item refund', [
                'order_item_id' => $orderItem->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => __('messages.refund_processing_failed'),
                'data' => []
            ];
        }
    }

    /**
     * Update order status after item cancellation
     *
     * @param Order $order The order to update
     * @return void
     */
    private
    function updateOrderStatusAfterCancellation(Order $order): void
    {
        app(FulfillmentRollupService::class)->recomputeOrderStatus($order);
    }

    public
    function getOrderTracking(User $user, string $orderSlug): array
    {
        try {
            $order = Order::where('slug', $orderSlug)
                ->where('user_id', $user->id)
                ->with(['items.product', 'items.variant', 'items.store'])
                ->first();

            if (!$order) {
                return [
                    'success' => false,
                    'message' => __('labels.order_not_found'),
                    'data' => []
                ];
            }

            if ((string)$order->fulfillment_status === (string)FulfillmentStatusEnum::FULFILLED()) {
                return [
                    'success' => true,
                    'message' => __('labels.order_delivered_already'),
                    'data' => [
                        "delivery_boy" => [],
                        "route" => null,
                        "order" => new OrderResource($order)
                    ]
                ];
            }

            return [
                "success" => true,
                "message" => __('labels.order_tracking_retrieved_successfully'),
                "data" => [
                    "delivery_boy" => [],
                    "route" => null,
                    "order" => new OrderResource($order)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => []
            ];
        }
    }

    public static function checkUserReviewExistByOrderItemId($id): bool
    {
        return Review::where(['order_item_id' => $id, 'user_id' => auth()->id()])->exists();
    }

    public static function getUserReviewByOrderItemId($id): ReviewResource|null
    {
        $review = Review::where(['order_item_id' => $id, 'user_id' => auth()->id()])->get()->first();
        if ($review) {
            return new ReviewResource($review);
        }
        return null;
    }
}
