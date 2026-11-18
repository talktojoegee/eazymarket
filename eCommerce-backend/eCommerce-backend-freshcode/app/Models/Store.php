<?php

namespace App\Models;

use App\Enums\SpatieMediaCollectionName;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Traits\HasSeoMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Store extends Model implements HasMedia
{
    use HasSeoMetadata, InteractsWithMedia, SoftDeletes;

    protected function seoFallbackDescription(): ?string
    {
        foreach (['description', 'about_us', 'promotional_text'] as $field) {
            $value = $this->{$field} ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return $this->seoFallbackTitle();
    }

    protected $appends = ['distance', 'store_logo', 'store_banner', 'avg_products_rating', 'avg_store_rating', 'total_store_feedback'];

    protected $fillable = [
        'seller_id',
        'name',
        'slug',
        'contact_email',
        'contact_number',
        'description',
        'tax_name',
        'tax_number',
        'currency_code',
        'promotional_text',
        'about_us',
        'return_replacement_policy',
        'refund_policy',
        'terms_and_conditions',
        'metadata',
        'verification_status',
        'visibility_status',
        'is_recommended',
        'pos_upi_vpa',
        'pos_upi_payee_name',
        'pos_payment_config',
        'receipt_template',
        'default_weight_kg',
        'default_length_cm',
        'default_breadth_cm',
        'default_height_cm',
    ];

    protected $casts = [
        'metadata' => 'array',
        'receipt_template' => 'array',
        'pos_payment_config' => 'array',
        'verification_status' => StoreVerificationStatusEnum::class,
        'visibility_status' => StoreVisibilityStatusEnum::class,
        'is_recommended' => 'boolean',
        'default_weight_kg' => 'decimal:3',
    ];

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = generateUniqueSlug(self::class, $value, 'slug', $this->id);
    }

    /**
     * Distance accessor — store no longer carries lat/lng, so this always
     * returns null. Closest-location distance lives on StoreLocation going
     * forward (Block 1B). Kept as a method-shaped no-op so existing
     * `$store->distance` reads don't blow up.
     */
    public function getDistanceAttribute()
    {
        return null;
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(StoreProductVariant::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(StoreInventoryLog::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(SellerFeedback::class, 'store_id');
    }

    public function storeVariantAddons(): HasMany
    {
        return $this->hasMany(StoreProductVariantAddon::class, 'store_id');
    }

    /**
     * Store-level inventory / pricing rows for addon items.
     */
    public function addonItems(): HasMany
    {
        return $this->hasMany(StoreAddonItem::class, 'store_id');
    }


    /**
     * Markets this store is enabled in. Empty pivot = the store isn't visible
     * to customers in any market — admin should pick at least one when saving.
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'store_market')->withTimestamps();
    }

    /**
     * Distinct countries the store may ship to — the union of every country
     * covered by the markets the store is enabled in. Shipping zones are
     * constrained to this set. An empty result means the store has no markets
     * (or no market countries) and callers should treat coverage as unbounded.
     */
    public function allowedShippingCountries(): \Illuminate\Support\Collection
    {
        return $this->markets()
            ->with('countries:id,iso2,name')
            ->get()
            ->pluck('countries')
            ->flatten()
            ->unique('iso2')
            ->sortBy('name')
            ->values();
    }

    /**
     * Shipping profiles this store owns. Each store has exactly one default
     * profile ("General profile") plus zero-or-more custom profiles.
     */
    public function shippingProfiles(): HasMany
    {
        return $this->hasMany(ShippingProfile::class);
    }

    public function defaultShippingProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ShippingProfile::class)->where('is_default', true);
    }

    /**
     * Physical fulfillment locations this store owns. One Store has N
     * locations; every store has exactly one is_default location that's
     * auto-seeded on create and undeletable.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(StoreLocation::class);
    }

    public function defaultLocation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StoreLocation::class)->where('is_default', true);
    }

    /**
     * Shopify-style package presets — sellers define named box/envelope
     * dimensions and pick one (or accept the default) at AWB time. The
     * default row is auto-seeded by StoreObserver alongside General profile
     * + Shop location.
     */
    public function packages(): HasMany
    {
        return $this->hasMany(StorePackage::class);
    }

    public function defaultPackage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StorePackage::class)->where('is_default', true);
    }


    public function getProductCountAttribute(): int
    {
        $storeId = $this->id ?? 0;

        return Product::whereHas('variants.storeProductVariants', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })->count();
    }

    public function getAvgProductsRatingAttribute()
    {
        return Review::whereHas('product.variants.storeProductVariants', function ($q) {
            $q->where('store_id', $this->id);
        })
            ->avg('rating');
    }

    public function getAvgStoreRatingAttribute()
    {
        return SellerFeedback::where('store_id', $this->id)->avg('rating');
    }

    public function getTotalStoreFeedbackAttribute()
    {
        return SellerFeedback::where('store_id', $this->id)->count();
    }

    public function getStoreLogoAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::STORE_LOGO()) ?? null;
    }

    public function getStoreBannerAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::STORE_BANNER()) ?? null;
    }



    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::STORE_LOGO())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::STORE_BANNER())->singleFile();
    }
}
