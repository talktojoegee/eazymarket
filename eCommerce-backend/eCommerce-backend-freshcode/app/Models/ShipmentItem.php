<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One order-item line included in a shipment. A shipment only ever contains the
 * seller order's ACCEPTED items (cancelled / rejected lines are excluded), so
 * this is the authoritative "what's physically in the box" list.
 */
class ShipmentItem extends Model
{
    protected $fillable = [
        'shipment_id',
        'order_item_id',
        'quantity',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
