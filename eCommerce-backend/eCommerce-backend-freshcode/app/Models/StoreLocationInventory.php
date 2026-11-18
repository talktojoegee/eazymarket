<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-location stock for a product variant. Composite PK on
 * (store_location_id, product_variant_id) — no surrogate id.
 *
 * `reserved` is the soft hold during checkout; available stock is
 * (stock - reserved). Use that when deciding whether a location can
 * fulfill a line.
 */
class StoreLocationInventory extends Model
{
    protected $table = 'store_location_inventory';

    /** Composite key — Eloquent doesn't handle this natively, so we turn off
     *  the auto-increment + cast the implicit "id" away. Queries should use
     *  ->where('store_location_id', X)->where('product_variant_id', Y).
     */
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'store_location_id',
        'product_variant_id',
        'is_fulfillment_location',
        'tracked',
        'stock',
        'low_stock_threshold',
        'reserved',
        'last_restocked_at',
    ];

    protected $casts = [
        'is_fulfillment_location' => 'boolean',
        'tracked' => 'boolean',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'reserved' => 'integer',
        'last_restocked_at' => 'datetime',
    ];

    public function storeLocation(): BelongsTo
    {
        return $this->belongsTo(StoreLocation::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** Convenience — stock available to allocate to a new line. */
    public function getAvailableAttribute(): int
    {
        return max(0, (int) $this->stock - (int) $this->reserved);
    }

    /**
     * True if this row can fulfill `$quantity` units right now.
     * Untracked rows are always available (Shopify-style: no count enforced).
     */
    public function canFulfill(int $quantity): bool
    {
        if (! $this->is_fulfillment_location) {
            return false;
        }
        if (! $this->tracked) {
            return true;
        }
        return $this->available >= $quantity;
    }
}
