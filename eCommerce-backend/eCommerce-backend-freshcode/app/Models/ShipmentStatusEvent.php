<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable row per shipment status transition (see the migration). Read by
 * OrderCustomerStatusPresenter to time the customer timeline's shipment events.
 */
class ShipmentStatusEvent extends Model
{
    protected $fillable = [
        'shipment_id',
        'status',
        'note',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
