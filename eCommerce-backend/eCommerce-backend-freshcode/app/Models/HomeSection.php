<?php

namespace App\Models;

use App\Enums\HomePage\ItemPurposeEnum;
use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class HomeSection extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $appends = ['background_image', 'hero_media', 'card_background_image'];

    protected $fillable = [
        'home_layout_id',
        'type',
        'title',
        'show_title',
        'style',
        'config',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'show_title' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(HomeLayout::class, 'home_layout_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HomeSectionItem::class)->orderBy('sort_order');
    }

    /** Items rendered as cards/entries (hero items, custom picks, banners). */
    public function displayItems(): HasMany
    {
        return $this->items()->where('purpose', ItemPurposeEnum::DISPLAY());
    }

    /** Items that constrain a query (e.g. a products section's category filter). */
    public function filterItems(): HasMany
    {
        return $this->items()->where('purpose', ItemPurposeEnum::FILTER());
    }

    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'home_section_market')->withTimestamps();
    }

    /** Optional ordering overrides used when a storefront is resolved for a market. */
    public function marketOrders(): HasMany
    {
        return $this->hasMany(HomeSectionMarketOrder::class, 'home_section_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /** Sections visible in the given market. Empty market pivot = visible everywhere. */
    public function scopeAvailableInMarket(Builder $query, ?int $marketId): Builder
    {
        if (is_null($marketId)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($marketId) {
            $q->whereDoesntHave('markets')
                ->orWhereHas('markets', fn (Builder $m) => $m->where('markets.id', $marketId));
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_SECTION_BACKGROUND())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_SECTION_CARD_BACKGROUND())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ROW_BACKGROUND());
    }

    public function getBackgroundImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_SECTION_BACKGROUND()) ?: null;
    }

    public function getHeroMediaAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA()) ?: null;
    }

    public function getCardBackgroundImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::HOME_SECTION_CARD_BACKGROUND()) ?: null;
    }

    protected static function booted(): void
    {
        static::deleting(function (HomeSection $section) {
            $section->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_BACKGROUND());
            $section->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA());
            $section->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_CARD_BACKGROUND());
            $section->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ROW_BACKGROUND());
        });
    }
}
