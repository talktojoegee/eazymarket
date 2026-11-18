<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingZoneServiceabilityRule extends Model
{
    protected $table = 'shipping_zone_serviceability';

    protected $fillable = [
        'shipping_zone_id',
        'country_iso2',
        'match_type',
        'value',
        'value_to',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
