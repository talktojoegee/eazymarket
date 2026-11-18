<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shopify-style package preset. A store has N rows; exactly one is
 * `is_default=true` and pre-fills the AWB shipment form. Sellers may
 * override per-parcel at fulfillment time.
 *
 * The legacy `stores.default_weight_kg / default_length_cm / default_breadth_cm
 * / default_height_cm` columns are kept as a denormalised cache of whatever
 * row is currently the default — see StorePackageObserver::saved.
 */
class StorePackage extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_BOX        = 'box';
    public const TYPE_ENVELOPE   = 'envelope';
    public const TYPE_SOFT_PACK  = 'soft_pack';

    public const VOLUMETRIC_DIVISOR = 5000;

    public function volumetricWeightKg(): float
    {
        return round(($this->length_cm * $this->breadth_cm * $this->height_cm) / self::VOLUMETRIC_DIVISOR, 3);
    }

    protected $fillable = [
        'store_id',
        'name',
        'type',
        'length_cm',
        'breadth_cm',
        'height_cm',
        'empty_weight_kg',
        'is_default',
    ];

    protected $casts = [
        'length_cm'       => 'integer',
        'breadth_cm'      => 'integer',
        'height_cm'       => 'integer',
        'empty_weight_kg' => 'decimal:3',
        'is_default'      => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
