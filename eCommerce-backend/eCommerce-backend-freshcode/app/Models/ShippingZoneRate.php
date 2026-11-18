<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single shipping rate / option attached to a ShippingZone. Shopify-style:
 * a zone may have many rates (e.g. "Standard $5, 3-5 days" + "Express $15,
 * 1-2 days"). The seller UI currently shows one per zone — the multi-rate
 * UX lands as a feature update without any schema change.
 *
 * The legacy zone-level rate/ETA columns (`shipping_zones.flat_rate` etc.)
 * are dormant after the rates table backfill — left in place because the
 * operator already migrated those tables.
 */
class ShippingZoneRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shipping_zone_id',
        'name',
        'rate_type',
        'flat_rate',
        'free_above',
        'weight_rules',
        'price_rules',
        'eta_min',
        'eta_max',
        'eta_unit',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'flat_rate'    => 'decimal:2',
        'free_above'   => 'decimal:2',
        'weight_rules' => 'array',
        'price_rules'  => 'array',
        'eta_min'      => 'integer',
        'eta_max'      => 'integer',
        'priority'     => 'integer',
        'is_active'    => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
