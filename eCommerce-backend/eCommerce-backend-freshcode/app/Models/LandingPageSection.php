<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LandingPageSection extends Model
{
    protected $fillable = ['landing_page_id', 'key', 'type', 'variant', 'preset', 'settings', 'sort_order', 'is_active'];

    protected $casts = ['settings' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'landing_page_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LandingPageSectionTranslation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LandingPageItem::class)->orderBy('sort_order');
    }

    public function mediaSlots(): MorphMany
    {
        return $this->morphMany(LandingPageMediaSlot::class, 'owner');
    }

    protected static function booted(): void
    {
        static::deleting(function (LandingPageSection $section) {
            $section->items()->get()->each->delete();
            $section->mediaSlots()->get()->each->delete();
        });
    }
}
