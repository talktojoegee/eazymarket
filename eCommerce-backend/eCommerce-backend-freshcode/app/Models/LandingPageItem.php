<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LandingPageItem extends Model
{
    protected $fillable = ['landing_page_section_id', 'key', 'preset', 'settings', 'sort_order'];

    protected $casts = ['settings' => 'array', 'sort_order' => 'integer'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(LandingPageSection::class, 'landing_page_section_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LandingPageItemTranslation::class);
    }

    public function mediaSlots(): MorphMany
    {
        return $this->morphMany(LandingPageMediaSlot::class, 'owner');
    }

    protected static function booted(): void
    {
        static::deleting(fn (LandingPageItem $item) => $item->mediaSlots()->get()->each->delete());
    }
}
