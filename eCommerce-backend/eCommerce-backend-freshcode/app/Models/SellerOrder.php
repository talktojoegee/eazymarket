<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerOrder extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'total_price',
        'status',
        'fulfillment_status',
        'market_id',
        'store_location_id',
        'shipping_zone_id',
        'fulfillment_method',
        'gross_subtotal',
        'shipping_charge_applied',
        'tax_amount',
        'promo_share',
        'free_shipping_threshold_applied',
        'shipping_clawback_applied',
        'promo_clawback_applied',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SellerOrderItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function storeLocation(): BelongsTo
    {
        return $this->belongsTo(StoreLocation::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(SellerFeedback::class, 'order_id', 'order_id');
    }
}
