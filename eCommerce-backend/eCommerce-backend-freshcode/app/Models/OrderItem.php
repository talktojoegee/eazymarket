<?php

namespace App\Models;

use App\Enums\Order\CancelReasonEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @method static whereBetween(string $string, array $array)
 * @method static where(string $string, $DELIVERED)
 */
class OrderItem extends Model implements HasMedia
{
    use InteractsWithMedia;
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'store_id',
        'title',
        'variant_title',
        'gift_card_discount',
        'admin_commission_amount',
        'seller_commission_amount',
        'commission_settled',
        'return_eligible',
        'return_deadline',
        'returnable_days',
        'is_cancelable',
        'cancelable_till',
        'discounted_price',
        'promo_discount',
        'discount',
        'tax_amount',
        'tax_percent',
        'sku',
        'quantity',
        'price',
        'subtotal',
        'eta_min',
        'eta_max',
        'eta_unit',
        'status',
        'fulfillment_status',
        'cancellation_reason',
        'cancel_reason',
        'is_restocked',
    ];

    protected $casts = [
        'return_eligible' => 'boolean',
        'return_deadline' => 'date',
        'eta_min' => 'integer',
        'eta_max' => 'integer',
        'is_cancelable' => 'boolean',
        'is_restocked' => 'boolean',
        'cancel_reason' => CancelReasonEnum::class,
    ];

    /**
     * Register media collections for order item.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS());
    }

    /**
     * Boot the model.
     *
     * Lifecycle is coarse now (active → cancelled / returned / refunded); the
     * detailed progression moved to the independent axes (fulfillment_status /
     * shipment status). The only guard left prevents mutating
     * a line once it has reached a terminal bucket.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($orderItem) {
            if (! $orderItem->isDirty('status')) {
                return;
            }

            $old = $orderItem->getOriginal('status');

            $terminal = [
                (string) OrderItemStatusEnum::CANCELLED(),
                (string) OrderItemStatusEnum::RETURNED(),
                (string) OrderItemStatusEnum::REFUNDED(),
            ];

            if ($old !== null && in_array((string) $old, $terminal, true)) {
                throw new \Exception('Order item status cannot change once it is "' . $old . '".');
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productReviews(): HasOne
    {
        return $this->hasOne(Review::class, 'order_item_id');
    }
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(OrderItemAddon::class);
    }

    public function comboComponents(): HasMany
    {
        return $this->hasMany(OrderItemComboComponent::class);
    }

    public function sellerFeedback(): HasOne
    {
        return $this->hasOne(SellerFeedback::class, 'order_item_id');

    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderItemReturn::class, 'order_item_id');
    }

    public function latestReturn(): HasOne
    {
        return $this->hasOne(OrderItemReturn::class)->latestOfMany();
    }

    public function sellerOrderItem(): HasOne
    {
        return $this->hasOne(SellerOrderItem::class);
    }

    /**
     * Parcel lines this order item appears in. Used to work out how many units are
     * already committed to a shipment (so the rest can still be cancelled).
     */
    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class, 'order_item_id');
    }

    public static function capturePayment(int $orderId): bool
    {
        try {
            $order = Order::find($orderId);
            if (!$order || (string) $order->payment_status !== (string) PaymentStatusEnum::COMPLETED()) {
                return false;
            }

            $orderItem = self::where('order_id', $orderId)->get();
            if ($orderItem->isNotEmpty()) {
                foreach ($orderItem as $item) {
                    $item->update([
                        'status' => OrderItemStatusEnum::ACTIVE()
                    ]);
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function paymentFailed(int $orderId): bool
    {
        try {
            $order = Order::find($orderId);
            if (!$order || (string) $order->payment_status !== (string) PaymentStatusEnum::FAILED()) {
                return false;
            }

            $orderItem = self::where('order_id', $orderId)->get();
            if ($orderItem->isNotEmpty()) {
                foreach ($orderItem as $item) {
                    $item->update([
                        'status' => OrderItemStatusEnum::CANCELLED()
                    ]);
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function scopeSellerFeedback($orderId, $sellerId)
    {
        return SellerFeedback::where('order_id', $orderId)->where('seller_id', $sellerId)->get()->first();
    }
}
