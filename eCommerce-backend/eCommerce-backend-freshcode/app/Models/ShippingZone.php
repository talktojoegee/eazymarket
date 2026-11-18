<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shipping zone — lives under a shipping profile (Shopify-style).
 *
 * Bundles method + rate type + ETA + coverage. Coverage is country-level: a
 * zone covers a set of countries. Within a manual zone, `serviceability_mode`
 * + serviceability rules carve out (deny) or restrict to (allow) specific
 * pincodes / cities / states inside those countries.
 */
class ShippingZone extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shipping_profile_id',
        'name',
        'method',
        'rate_type',
        'flat_rate',
        'free_above',
        'weight_rules',
        'price_rules',
        'eta_min',
        'eta_max',
        'eta_unit',
        'operating_hours',
        'pickup_instructions',
        'is_active',
        'serviceability_mode',
        'priority',
    ];

    protected $casts = [
        'flat_rate' => 'decimal:2',
        'free_above' => 'decimal:2',
        'weight_rules' => 'array',
        'price_rules' => 'array',
        'operating_hours' => 'array',
        'eta_min' => 'integer',
        'eta_max' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ShippingProfile::class, 'shipping_profile_id');
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'shipping_zone_countries', 'shipping_zone_id', 'country_iso2', 'id', 'iso2')
            ->withTimestamps();
    }

    public function serviceabilityRules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShippingZoneServiceabilityRule::class);
    }

    /**
     * Shopify-style rates/options under this zone. Storage is many-to-one
     * even though the seller UI currently caps at one rate per zone — the
     * multi-rate UI is a pure frontend change.
     */
    public function rates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShippingZoneRate::class)
            ->orderBy('priority')
            ->orderBy('id');
    }

    /**
     * Convenience accessor — the rate the seller created for this zone,
     * resolved as the first by priority. Phase 1 of multi-rates will
     * replace callers of this with explicit picker logic; for now this
     * lets the UI / router pretend each zone has exactly one rate.
     */
    public function defaultRate(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ShippingZoneRate::class)
            ->orderBy('priority')
            ->orderBy('id');
    }
}
