<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductVariant extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $appends = ['image', 'price_exclude_tax', 'special_price_exclude_tax'];
    protected $fillable = [
        'uuid',
        'product_id',
        'title',
        'slug',
        'weight',
        'height',
        'breadth',
        'length',
        'availability',
        'provider',
        'provider_product_id',
        'provider_json',
        'barcode',
        'sku',
        'price',
        'special_price',
        'cost',
        'visibility',
        'is_default'
    ];

    protected $casts = [
        'provider_json' => 'array',
        'availability' => 'boolean',
        'is_default' => 'boolean',
        'price' => 'decimal:2',
        'special_price' => 'decimal:2',
        'cost' => 'decimal:2',
    ];

    /**
     * Pricing is variant-scoped. Tax adjustment used to live on
     * StoreProductVariant and ran on read; same behaviour here so resources
     * keep emitting tax-included values when the product is exclusive-tax.
     */
    /**
     * Apply the customer's market tax classes to a raw price.
     *
     * Tax classes are market-scoped (`tax_classes.market_id`). Summing every
     * class attached to a product — which the old implementation did — would
     * double-tax (or wrong-tax) a customer whose market is different from the
     * class's market. We now filter `$product->taxClasses` by the request's
     * resolved market before summing rates. When no class matches the market,
     * the price passes through unmodified (no tax in that market).
     *
     * Admin/CLI contexts (no resolved request market) fall back to the legacy
     * "sum every attached class" behaviour so the seller panel still shows
     * the full tax-inclusive price the merchant configured.
     */
    private function getTaxAdjustedPrice($price): ?float
    {
        if ($price === null) {
            return null;
        }

        $product = $this->product;
        if (! $product || (int) $product->is_inclusive_tax !== 0) {
            return (float) $price;
        }

        $taxClasses = $product->relationLoaded('taxClasses')
            ? $product->taxClasses
            : $product->taxClasses()->get();

        if ($taxClasses === null || $taxClasses->isEmpty()) {
            return (float) $price;
        }

        $market = request()?->attributes->get('market');
        if ($market instanceof Market) {
            $taxClasses = $taxClasses->where('market_id', $market->id);
        }

        if ($taxClasses->isEmpty()) {
            return (float) $price;
        }

        $totalTaxRate = $taxClasses->sum(function ($taxClass) {
            return $taxClass->taxRates->sum('rate');
        });

        return (float) $price + ((float) $price * $totalTaxRate / 100);
    }

    public function getPriceAttribute($value): ?float
    {
        return $this->getTaxAdjustedPrice($value);
    }

    public function getSpecialPriceAttribute($value): ?float
    {
        return $this->getTaxAdjustedPrice($value);
    }

    public function getPriceExcludeTaxAttribute(): ?float
    {
        return isset($this->attributes['price']) ? (float) $this->attributes['price'] : null;
    }

    public function getSpecialPriceExcludeTaxAttribute(): ?float
    {
        return isset($this->attributes['special_price']) ? (float) $this->attributes['special_price'] : null;
    }

    public function getImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::VARIANT_IMAGE());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class, 'product_variant_id');
    }

    /**
     * Stores that carry this variant. Now a thin mapping (no pricing/stock):
     * pricing lives on the variant itself; per-location inventory lives on
     * locationInventories().
     */
    public function storeProductVariants(): HasMany
    {
        return $this->hasMany(StoreProductVariant::class, 'product_variant_id');
    }

    /**
     * Per-location inventory rows. One per (variant, store_location) where the
     * seller has opted in to either sell from / track stock at that location.
     */
    public function locationInventories(): HasMany
    {
        return $this->hasMany(StoreLocationInventory::class, 'product_variant_id');
    }

    /**
     * Stock available across a specific store's fulfillment-eligible locations.
     *
     *   - Returns null when at least one fulfillment-eligible row is untracked
     *     (Shopify: untracked = always-available; we communicate "no count" as null).
     *   - Returns the summed `(stock - reserved)` across tracked rows otherwise.
     *   - Pass null for $storeId to aggregate across every store that carries it.
     */
    public function stockForStore(?int $storeId = null): ?int
    {
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;
        $productHasMetadata = $product && array_key_exists('metadata', $product->getAttributes());
        $isKit = $product && ($product->type === 'kit' || data_get($product->metadata, 'is_combo_pack', false));
        $comboPackId = $productHasMetadata && ! $isKit
            ? 0
            : (int) Cache::remember(
                'combo-pack-product:'.$this->product_id,
                now()->addMinutes(10),
                fn () => (int) ComboPack::where('product_id', $this->product_id)->value('id'),
            );
        $comboPack = $comboPackId > 0 ? ComboPack::with('items.variant')->find($comboPackId) : null;
        if ($comboPack) {
            $components = $comboPack->items;
            if ($components->isEmpty() || $components->contains(fn ($item) => ! $item->variant)) {
                return 0;
            }

            $componentIds = $components->pluck('product_variant_id')->map(fn ($id) => (int) $id)->all();
            $locationsQuery = StoreLocation::query()
                ->where('is_active', true)
                ->where('fulfills_online_orders', true)
                ->whereHas('inventory', fn ($query) => $query
                    ->where('product_variant_id', $this->id)
                    ->where('is_fulfillment_location', true));
            if ($storeId !== null) {
                $locationsQuery->where('store_id', $storeId);
            }
            $locations = $locationsQuery
                ->with(['inventory' => fn ($query) => $query
                    ->whereIn('product_variant_id', $componentIds)
                    ->where('is_fulfillment_location', true)])
                ->get();

            $locationAvailability = $locations->map(function (StoreLocation $location) use ($components) {
                $inventoryByVariant = $location->inventory->keyBy('product_variant_id');
                $trackedAvailability = [];

                foreach ($components as $component) {
                    $inventory = $inventoryByVariant->get((int) $component->product_variant_id);
                    if (! $inventory) {
                        return 0;
                    }
                    if ($inventory->tracked) {
                        $trackedAvailability[] = intdiv(
                            max(0, (int) $inventory->stock - (int) $inventory->reserved),
                            max(1, (int) $component->quantity),
                        );
                    }
                }

                return $trackedAvailability === [] ? null : min($trackedAvailability);
            });

            if ($locationAvailability->contains(fn ($stock) => $stock === null)) {
                return null;
            }

            return (int) $locationAvailability->sum();
        }

        $query = StoreLocationInventory::query()
            ->where('product_variant_id', $this->id)
            ->where('is_fulfillment_location', true);

        if ($storeId !== null) {
            $query->whereHas('storeLocation', fn ($q) => $q->where('store_id', $storeId));
        }

        $rows = $query->get(['stock', 'reserved', 'tracked']);
        if ($rows->isEmpty()) {
            return 0;
        }
        if ($rows->contains(fn ($r) => ! $r->tracked)) {
            return null;
        }
        return (int) $rows->sum(fn ($r) => max(0, (int) $r->stock - (int) $r->reserved));
    }

    /**
     * Convenience boolean for cart/PDP — "can this be ordered right now?"
     */
    public function isInStockForStore(?int $storeId = null): bool
    {
        $s = $this->stockForStore($storeId);
        return $s === null || $s > 0;
    }

    /**
     * The StoreLocation that's backing the rendered stock for this variant.
     *
     * Pick order matches what a customer would see "first":
     *   1. A tracked fulfillment row with available stock (stock - reserved > 0),
     *      preferring the row with the most available stock so the chosen
     *      location is also the one most likely to ship.
     *   2. Any untracked fulfillment row (Shopify-style "always available").
     *   3. Otherwise null — no location can fulfill right now.
     *
     * Use this when you need to expose *where* the inventory is held (e.g.,
     * `store_location_id` / `store_location_name` on the variant resource),
     * NOT for routing the order — `FulfillmentRouterService` owns that.
     */
    public function fulfillmentLocationForStore(?int $storeId = null): ?StoreLocation
    {
        $query = StoreLocationInventory::query()
            ->with('storeLocation:id,store_id,name')
            ->where('product_variant_id', $this->id)
            ->where('is_fulfillment_location', true);

        if ($storeId !== null) {
            $query->whereHas('storeLocation', fn ($q) => $q->where('store_id', $storeId));
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return null;
        }

        $tracked = $rows->where('tracked', true)
            ->filter(fn ($r) => ((int) $r->stock - (int) $r->reserved) > 0)
            ->sortByDesc(fn ($r) => (int) $r->stock - (int) $r->reserved);

        if ($tracked->isNotEmpty()) {
            return $tracked->first()->storeLocation;
        }

        $untracked = $rows->firstWhere('tracked', false);
        return $untracked?->storeLocation;
    }

    public function storeVariantAddons(): HasMany
    {
        return $this->hasMany(StoreProductVariantAddon::class, 'product_variant_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'product_variant_id');
    }

    public function isInUserCart(): array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return ['exists' => false, 'cart_item_id' => null];
        }

        $cartItem = $this->cartItems()
            ->whereHas('cart', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        return [
            'exists' => !is_null($cartItem),
            'cart_item_id' => $cartItem?->id
        ];
    }

    /**
     * Get store product variant by store ID
     */
    public function storeProductVariantByStore(int $storeId): HasOne
    {
        return $this->hasOne(StoreProductVariant::class, 'product_variant_id')
            ->where('store_id', $storeId);
    }

    protected static function booted(): void
    {
        parent::boot();

        static::deleting(function ($variant) {
            CartItem::where('product_variant_id', $variant->id)->delete();
        });

        static::saving(function ($model) {
            $model->slug = generateUniqueSlug(
                model: self::class,
                title: $model->title,
                id: $model->id
            );
        });
    }
}
