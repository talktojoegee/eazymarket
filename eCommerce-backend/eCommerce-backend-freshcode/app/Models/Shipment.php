<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical shipment for one seller_order (= one location+zone shipment group).
 *
 * `status` is the granular delivery state machine (ShipmentStatusEnum) and is
 * the SOURCE OF TRUTH for delivery progress. The coarse `fulfillment_status` on
 * order / seller_order / order_item is derived FROM this by
 * FulfillmentRollupService.
 *
 * @method static create(array $attributes)
 */
class Shipment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'seller_order_id',
        'store_location_id',
        'provider_code',
        'shipping_zone_id',
        'awb_number',
        'courier_id',
        'courier_name',
        'manual_carrier_name',
        'tracking_number',
        'label_url',
        'invoice_url',
        'manifest_url',
        'tracking_url',
        'weight_kg',
        'length_cm',
        'breadth_cm',
        'height_cm',
        'shipping_cost',
        'provider_shipping_cost',
        'shipping_cost_paid_by',
        'shipping_currency',
        'payment_method',
        'cod_amount',
        'is_return',
        'parent_shipment_id',
        'status',
        'pickup_scheduled_at',
        'picked_up_at',
        'delivered_at',
        'last_event_at',
        'metadata',
        'raw_response',
    ];

    protected $casts = [
        'is_return'           => 'boolean',
        'weight_kg'           => 'float',
        'shipping_cost'       => 'float',
        'provider_shipping_cost' => 'float',
        'cod_amount'          => 'float',
        'metadata'            => 'array',
        'raw_response'        => 'array',
        'pickup_scheduled_at' => 'datetime',
        'picked_up_at'        => 'datetime',
        'delivered_at'        => 'datetime',
        'last_event_at'       => 'datetime',
    ];

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function storeLocation(): BelongsTo
    {
        return $this->belongsTo(StoreLocation::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    /** Per-transition status history (oldest first). */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(ShipmentStatusEvent::class)->orderBy('occurred_at');
    }

    /**
     * Append a status-history row. Pass a status to record explicitly, otherwise
     * the shipment's current status is used. Safe to call after every transition.
     */
    public function recordStatusEvent(?string $status = null, ?string $note = null): void
    {
        $this->statusEvents()->create([
            'status'      => $status ?? (string) $this->status,
            'note'        => $note,
            'occurred_at' => now(),
        ]);
    }
}
