<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LandingPage extends Model
{
    protected $fillable = ['key', 'schema_version', 'is_active'];

    protected $casts = ['schema_version' => 'integer', 'is_active' => 'boolean'];

    public function translations(): HasMany
    {
        return $this->hasMany(LandingPageTranslation::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(LandingPageSection::class)->orderBy('sort_order');
    }

    public function mediaSlots(): MorphMany
    {
        return $this->morphMany(LandingPageMediaSlot::class, 'owner');
    }

    protected static function booted(): void
    {
        static::deleting(function (LandingPage $page) {
            $page->sections()->get()->each->delete();
            $page->mediaSlots()->get()->each->delete();
        });
    }
}
