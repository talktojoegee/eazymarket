<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class LandingPageMediaSlot extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const COLLECTION = 'landing_page_asset';

    protected $fillable = ['owner_type', 'owner_id', 'slot', 'use_default'];

    protected $casts = ['use_default' => 'boolean'];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)->singleFile();
    }

    public function url(): string
    {
        return $this->use_default ? '' : $this->getFirstMediaUrl(self::COLLECTION);
    }
}
