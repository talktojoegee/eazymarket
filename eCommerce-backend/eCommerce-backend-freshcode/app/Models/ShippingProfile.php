<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shipping profile — Shopify-style "General profile" + custom profiles.
 *
 * Owns:
 *   - locations(): which store_locations are allowed to ship under this profile
 *   - products():   which products this profile applies to (UNIQUE per product)
 *   - zones():      shipping_zones live under a profile, not a location
 *
 * Constraints (enforced in the observer + service layer, not in DB):
 *   - Every Store has exactly one row with is_default=true
 *   - The default profile cannot be deleted
 *   - A product belongs to at most one profile (UNIQUE on shipping_profile_products.product_id)
 */
class ShippingProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'slug',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(
            StoreLocation::class,
            'shipping_profile_store_location',
            'shipping_profile_id',
            'store_location_id'
        )->withPivot('priority')->withTimestamps()->orderByPivot('priority', 'asc');;
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'shipping_profile_products')
            ->withTimestamps();
    }

    public function zones(): HasMany
    {
        return $this->hasMany(ShippingZone::class);
    }
}
