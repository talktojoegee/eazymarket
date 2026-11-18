<?php

namespace App\Models;

use App\Enums\Shipping\ShiprocketSyncStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * StoreLocation — a Store's physical fulfillment point.
 *
 * Shopify model: a "Location" is owned by a single Shop. We mirror that —
 * one StoreLocation belongs to exactly one Store. If the same physical
 * address is used by two stores under the same seller, those are two rows.
 *
 * Constraints:
 *  - Every Store has exactly one row with is_default=true (auto-seeded by
 *    StoreObserver::created and protected by StoreLocationObserver).
 *  - The default location cannot be deleted.
 */
class StoreLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'store_locations';

    protected $fillable = [
        'store_id',
        'name',
        'address_1',
        'address_2',
        'city',
        'state',
        'country_iso2',
        'country_name',
        'pin_code',
        'latitude',
        'longitude',
        'shiprocket_pickup_location_id',
        'is_registered_with_shiprocket',
        'shiprocket_registered_at',
        'shiprocket_sync_hash',
        'shiprocket_sync_status',
        'shiprocket_sync_error',
        'is_physical_storefront',
        'fulfills_online_orders',
        'offers_local_delivery',
        'offers_local_pickup',
        'offers_pos',
        'is_active',
        'is_default',
        'status',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_registered_with_shiprocket' => 'boolean',
        'shiprocket_registered_at' => 'datetime',
        'is_physical_storefront' => 'boolean',
        'fulfills_online_orders' => 'boolean',
        'offers_local_delivery' => 'boolean',
        'offers_local_pickup' => 'boolean',
        'offers_pos' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function shippingProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            ShippingProfile::class,
            'shipping_profile_store_location',
            'store_location_id',
            'shipping_profile_id'
        )->withTimestamps();
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(StoreLocationInventory::class, 'store_location_id');
    }

    public function generateShiprocketNickname(): string
    {
        $slug = \Illuminate\Support\Str::slug($this->store->name ?? 'store');
        $slug = substr($slug, 0, 14) ?: 'store';

        return $slug . '-' . $this->id . '-' . now()->timestamp;
    }

    public function hasCompleteShiprocketAddress(): bool
    {
        return filled($this->address_1)
            && filled($this->city)
            && filled($this->state)
            && filled($this->pin_code);
    }

    public function shiprocketSyncHash(): string
    {
        return hash('sha256', implode('|', [
            $this->address_1,
            $this->address_2,
            $this->city,
            $this->state,
            $this->pin_code,
            $this->country_iso2,
        ]));
    }

    public function isShiprocketRegistered(): bool
    {
        return $this->is_registered_with_shiprocket
            && $this->shiprocket_sync_status === ShiprocketSyncStatusEnum::REGISTERED()
            && $this->shiprocket_sync_hash === $this->shiprocketSyncHash();
    }

    public function needsShiprocketSync(): bool
    {
        return !$this->isShiprocketRegistered();
    }
}
