<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static create(array $array)
 * @method static count()
 * @method static find($id)
 */
class TaxClass extends Model
{

    protected $table = 'tax_classes';

    protected $fillable = [
        'title',
        'market_id',
    ];

    /**
     * The market this tax class is scoped to. Sellers see only tax classes whose
     * market_id is in the seller's enabled-markets set when picking a tax class
     * for a product.
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * The tax rates that belong to the tax class.
     */
    public function taxRates()
    {
        return $this->belongsToMany(
            TaxRate::class,
            'tax_class_tax_rate',
            'tax_class_id',
            'tax_rate_id'
        )->withTimestamps();
    }
}
