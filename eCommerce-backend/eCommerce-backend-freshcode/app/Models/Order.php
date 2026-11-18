<?php

namespace App\Models;

use App\Enums\Order\OrderCancellationStateEnum;
use App\Enums\Order\OrderCreatedByEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Events\Order\OrderPlaced;
use App\Notifications\OrderPaymentStatusChanged;
use App\Services\Fx\FxRateService;
use App\Services\OrderInventoryService;
use App\Services\WalletService;
use App\Support\PricePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

/**
 * @method static create(array $array)
 */
class Order extends Model
{
    protected $fillable = [
        'uuid',
        'idempotency_key',
        'user_id',
        'slug',
        'order_number',
        'email',
        'ip_address',
        'currency_code',
        'currency_rate',
        'payment_method',
        'payment_status',
        'wallet_balance',
        'promo_code',
        'promo_discount',
        'gift_card',
        'gift_card_discount',
        'delivery_charge',
        'platform_fee',
        'cod_fee',
        'subtotal',
        'total_payable',
        'final_total',
        'status',
        'stock_status',
        'fulfillment_status',
        'billing_name',
        'billing_address_1',
        'billing_address_2',
        'billing_zip',
        'billing_phone',
        'billing_address_type',
        'billing_city',
        'billing_state',
        'billing_country',
        'billing_country_code',
        'shipping_name',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_zip',
        'shipping_phone',
        'shipping_address_type',
        'shipping_city',
        'shipping_state',
        'shipping_country',
        'shipping_country_code',
        'order_note',
        'is_flagged',
        'escalation_reasons',
        'escalated_at',
        'created_by',
        'walkin_customer_name',
        'walkin_customer_mobile',
        'pos_savings',
        'pos_split_cash',

        'market_id',
        'shipping_total',
        'tax_total',
        'tax_breakdown',
        'total_shipping_clawback',
        'total_promo_clawback',
        'total_pending_charge_created',
        'cancellation_state',
        'last_cancellation_at',
        'resolved_at',
        'order_summary',
    ];

    protected $casts = [
        'created_by' => OrderCreatedByEnum::class,
        'is_flagged' => 'boolean',
        'escalation_reasons' => 'array',
        'escalated_at' => 'datetime',
        'tax_breakdown' => 'array',
        'platform_fee' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_shipping_clawback' => 'decimal:2',
        'total_promo_clawback' => 'decimal:2',
        'total_pending_charge_created' => 'decimal:2',
        'resolved_at' => 'datetime',
        'cancellation_state' => OrderCancellationStateEnum::class,
        'last_cancellation_at' => 'datetime',
        'order_summary' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The market this order was placed in. Locked to the shipping address country
     * (Shopify Markets parity) — see CartService::lockMarketAtCheckout.
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function sellerFeedbacks(): HasMany
    {
        return $this->hasMany(SellerFeedback::class, 'order_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(OrderPaymentTransaction::class);
    }

    public function promoLine(): HasOne
    {
        return $this->hasOne(OrderPromoLine::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderItemReturn::class);
    }

    /**
     * Per-order refund records — see {@see OrderRefund}. Created by the cancel
     * flows whenever money is or will be returned to the customer.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class);
    }

    /**
     * What the customer has actually paid us so far, expressed in the order's
     * market currency. Two sources contribute:
     *
     *   1. Wallet portion — `order.wallet_balance`, already in market
     *      currency (per [[currency-units-wallet-vs-order]]). This is what
     *      the customer redeemed from their wallet at checkout. No
     *      OrderPaymentTransaction is written for it, so without including
     *      it here, full-wallet orders look unpaid and the refund page can't
     *      tell there's money on the table.
     *   2. Gateway portion — sum of OrderPaymentTransaction rows in
     *      `completed`/`paid` state. Each row is stamped with the currency
     *      the gateway charged in (Razorpay → INR, Stripe US → USD, …)
     *      which is usually NOT the order's market currency, so we convert
     *      per-row via FxRateService before summing.
     *
     * For a £100 GBP order paid £20 from wallet + ₹8,400 INR via Razorpay,
     * the accessor returns £100, not £20 + ₹8,400 mashed together.
     */
    public function getAmountPaidAttribute(): float
    {
        $paidStatuses = [
            (string)PaymentStatusEnum::COMPLETED()
        ];

        $orderCurrency = strtoupper((string)$this->currency_code);

        $walletPortion = (float)($this->wallet_balance ?? 0);
        $walletMethod = (string)PaymentTypeEnum::WALLET();
        $gatewayTransactionsQuery = $this->paymentTransactions()
            ->whereIn('payment_status', $paidStatuses)
            ->where(function ($q) use ($walletMethod) {
                $q->where('payment_method', '!=', $walletMethod)
                    ->orWhereNull('payment_method');
            });

        if ($orderCurrency === '') {
            $txTotal = (float)$gatewayTransactionsQuery->sum('amount');
            return round($walletPortion + $txTotal, 2);
        }

        $fx = app(FxRateService::class);
        $total = $walletPortion;
        $transactions = $gatewayTransactionsQuery->get(['amount', 'currency']);

        foreach ($transactions as $tx) {
            $amount = (float)$tx->amount;
            $txCurrency = strtoupper((string)($tx->currency ?? ''));

            if ($txCurrency === '' || $txCurrency === $orderCurrency) {
                $total += $amount;
                continue;
            }

            $converted = $fx->convertCurrency($amount, $txCurrency, $orderCurrency);
            if ($converted === null) {
                Log::warning('Order::amount_paid — no FX rate, transaction excluded', [
                    'order_id' => $this->id,
                    'from' => $txCurrency,
                    'to' => $orderCurrency,
                    'amount' => $amount,
                ]);
                continue;
            }

            $total += (float)$converted;
        }

        return round($total, 2);
    }

    /**
     * Sum of refunds we've actually paid back — `issued` rows only. Used to
     * compute outstanding refund obligation alongside {@see amount_refund_owed}.
     */
    public function getAmountRefundedAttribute(): float
    {
        return (float)$this->refunds()
            ->where('status', (string)RefundStatusEnum::ISSUED())
            ->sum('amount');
    }

    /**
     * Currency payload (symbol + format rules) for this order's market —
     * what `formatOrderMoney()` needs. Exposed as an accessor so any view or
     * resource can pull it via `$order->currency` / `$order['currency']`
     * without having to inject it separately. Returns null for legacy orders
     * with no market (which makes formatOrderMoney fall back to the platform
     * default — same behaviour as before).
     */
    public function getCurrencyAttribute(): ?array
    {
        $market = $this->relationLoaded('market') ? $this->getRelation('market') : $this->market;
        if (!$market) {
            return null;
        }
        return PricePresenter::payload($market);
    }

    /**
     * Refund money recorded but not yet returned to the customer. Excludes
     * OWED rows that have been paid off by a later refund — those rows stay
     * in the table for audit but stop counting toward the outstanding
     * obligation (see `OrderRefund.settled_by_refund_id`).
     */
    public function getAmountRefundOwedAttribute(): float
    {
        return (float)$this->refunds()
            ->where('status', (string)RefundStatusEnum::OWED())
            ->whereNull('settled_by_refund_id')
            ->sum('amount');
    }

    /**
     * A capture arriving after the order was cancelled or its reservation
     * released (expiry cron, customer cancel-payment, superseded by a newer
     * order) must not revive the order: the stock is gone and any wallet
     * portion has already been credited back. Refuse and shout — the money is
     * recorded on the payment transaction and needs a gateway refund.
     */
    protected static function canCapture(Order $order): bool
    {
        $blocked = (string) $order->status === (string) OrderStatusEnum::CANCELLED()
            || $order->stock_status === OrderInventoryService::STATUS_RELEASED;

        if ($blocked) {
            Log::critical('Late payment capture blocked for a cancelled or released order', [
                'order_id' => $order->id,
                'status' => (string) $order->status,
                'stock_status' => $order->stock_status,
                'payment_status' => (string) $order->payment_status,
                'wallet_balance' => $order->wallet_balance,
            ]);
        }

        return !$blocked;
    }

    public static function capturePayment(int $orderId): bool
    {
        try {
            $order = self::find($orderId);
            if ($order) {
                if (!self::canCapture($order)) {
                    return false;
                }

                $alreadyPaid = (string) $order->payment_status === (string) PaymentStatusEnum::COMPLETED();
                $order->payment_status = PaymentStatusEnum::COMPLETED();
                $order->status = OrderStatusEnum::OPEN();
                $saved = $order->save();
                app(OrderInventoryService::class)->commit($order);
                if (! $alreadyPaid) {
                    self::consumePromoUsage($order);
                }
                self::notifyPaymentState($order, OrderPaymentStatusChanged::STATE_PAID);
                if (! $alreadyPaid) {
                    self::announceOrderPlaced($order);
                }
                return $saved;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Promo usage is consumed when the payment is captured, not when the order
     * is created, so abandoned and failed orders never burn a promo's limit.
     * Callers must only invoke this on the pending → paid transition.
     */
    protected static function consumePromoUsage(Order $order): void
    {
        foreach (OrderPromoLine::where('order_id', $order->id)->get() as $line) {
            Promo::where('id', $line->promo_id)->increment('usage_count');
        }
    }

    public static function capturePaymentFromOrder(Order $order): bool
    {
        try {
            if (!$order) {
                return false;
            }

            if (!self::canCapture($order)) {
                return false;
            }

            $alreadyPaid = (string) $order->payment_status === (string) PaymentStatusEnum::COMPLETED();
            $order->payment_status = PaymentStatusEnum::COMPLETED();
            $order->status = OrderStatusEnum::OPEN();

            $saved = $order->save();
            app(OrderInventoryService::class)->commit($order);
            if (! $alreadyPaid) {
                self::consumePromoUsage($order);
                self::announceOrderPlaced($order);
            }

            return $saved;
        } catch (\Exception $e) {
            Log::error('Order payment capture failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'exception' => $e
            ]);
            return false;
        }
    }


    /**
     * Give back the wallet money the customer redeemed at checkout. Reachable
     * from several dead-order paths (gateway failure webhook, customer
     * cancel-payment, expiry cron, superseded order) and a gateway can send one
     * failure event per declined attempt, so the credit is keyed per order —
     * `WalletService::addBalance` returns the existing transaction instead of
     * crediting a second time.
     */
    public static function refundWalletPortion(Order $order): void
    {
        if ($order->wallet_balance > 0) {
            $walletBaseAmount = ((float)$order->currency_rate) > 0
                ? round(((float)$order->wallet_balance) / (float)$order->currency_rate, 2)
                : (float)$order->wallet_balance;
            app(WalletService::class)->addBalance($order->user_id, [
                'amount' => $walletBaseAmount,
                'payment_method' => "Refund",
                'idempotency_key' => "order_{$order->id}_wallet_refund",
                'description' => "Order #{$order->id}: wallet amount of {$order->currency_code} {$order->wallet_balance} refunded."
            ]);
        }
    }

    public static function paymentFailed(int $orderId): bool
    {
        try {
            $order = self::find($orderId);
            if (!$order) {
                return false;
            }

            if ((string) $order->payment_status !== (string) PaymentStatusEnum::PENDING()) {
                Log::warning('Payment-failure signal ignored for an order that is no longer pending', [
                    'order_id' => $order->id,
                    'payment_status' => (string) $order->payment_status,
                    'status' => (string) $order->status,
                ]);
                return false;
            }

            self::refundWalletPortion($order);
            $order->payment_status = PaymentStatusEnum::FAILED();
            $order->status = OrderStatusEnum::OPEN();
            $saved = $order->save();
            app(OrderInventoryService::class)->release($order);
            self::notifyPaymentState($order, OrderPaymentStatusChanged::STATE_FAILED);
            return $saved;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * OrderPlaced announces a CONFIRMED order, so it fires on the pending → paid
     * transition for every prepaid method. COD orders have no such transition —
     * OrderService fires theirs at creation — so they are skipped here to avoid a
     * second announcement when an admin later marks the cash as received.
     */
    private static function announceOrderPlaced(Order $order): void
    {
        if ((string) $order->payment_method === (string) PaymentTypeEnum::COD()) {
            return;
        }

        try {
            $order->loadMissing(['items.product', 'items.variant', 'items.store', 'user', 'sellerOrders.seller.user']);
            event(new OrderPlaced($order));
        } catch (\Throwable $e) {
            Log::error('Order placed announcement failed: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
    }

    private static function notifyPaymentState(Order $order, string $state): void
    {
        try {
            $order->user?->notify(new OrderPaymentStatusChanged($order, $state));
        } catch (\Throwable $e) {
            Log::error('Order payment notification failed: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
    }

    public function isCustomerOrder(): bool
    {
        return $this->created_by === null
            || $this->created_by === OrderCreatedByEnum::CUSTOMER;
    }

    public function isPosOrder(): bool
    {
        return $this->created_by === OrderCreatedByEnum::SELLER;
    }

    public function isAttachedToWalkinPlaceholder(): bool
    {
        return $this->user_id === \App\Models\Setting::posWalkinUserId();
    }
}
