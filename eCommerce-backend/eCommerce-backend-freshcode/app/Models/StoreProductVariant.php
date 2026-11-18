<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Thin mapping that records "this store carries this variant".
 *
 * Pricing (price/special_price/sku/cost) moved to ProductVariant — the variant
 * is the single source of truth, one price across every store/location that
 * carries it. Stock was already removed in 2026_05_30_100014 and the rest of
 * the pricing columns in 2026_05_31_100003; this model now exists purely to
 * answer "does store X carry variant Y".
 *
 * Per-location inventory + fulfillment selection lives on store_location_inventory
 * (see StoreLocationInventory model). Code that used to read $spv->price should
 * read $spv->productVariant->price now.
 * @property mixed $store
 */
class StoreProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_variant_id',
        'store_id',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function getPriceAttribute(): ?float
    {
        return $this->productVariant?->price;
    }

    public function getSpecialPriceAttribute(): ?float
    {
        return $this->productVariant?->special_price;
    }

    /**
     * Proxy the pre-tax price accessors to the underlying ProductVariant.
     * Without these, reads like `$storeVariant->special_price_exclude_tax`
     * (used by OrderService when stamping order_items / seller_order_items)
     * resolve to null on the store-level model and silently become 0 —
     * which is what zeroed out the persisted line `price`.
     */
    public function getPriceExcludeTaxAttribute(): ?float
    {
        return $this->productVariant?->price_exclude_tax;
    }

    public function getSpecialPriceExcludeTaxAttribute(): ?float
    {
        return $this->productVariant?->special_price_exclude_tax;
    }

    public function getCostAttribute(): ?float
    {
        return $this->productVariant?->cost;
    }

    public function getSkuAttribute(): ?string
    {
        return $this->productVariant?->sku;
    }

    public function getStockAttribute(): ?int
    {
        return $this->productVariant?->stockForStore((int) $this->store_id);
    }

    public function getIsInStockAttribute(): bool
    {
        $s = $this->stock;
        return $s === null || $s > 0;
    }

    /**
     * Commission lookup helper retained for OrderService / settlement code that
     * resolves category commission from a variant's product.
     */
    public function getCategoryCommissionAttribute()
    {
        return DB::table('store_product_variants')->select(['categories.id', 'categories.commission'])
            ->join('product_variants', 'store_product_variants.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('store_product_variants.id', $this->id)->first();
    }

    /**
     * Tax-percentage helper kept here for backwards compatibility — call sites
     * pass base price + price-with-tax to compute the implied percentage.
     */
    public static function scopeTaxPercentage($basePrice, $priceWithTax): int
    {
        if ($basePrice == 0) {
            return 0;
        }
        $taxPercentage = (($priceWithTax - $basePrice) / $basePrice) * 100;
        return round($taxPercentage, 2);
    }
}
