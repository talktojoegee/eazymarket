<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class LandingPageBuilderDocument extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const MEDIA_COLLECTION = 'landing_page_builder_assets';

    protected $fillable = ['title', 'slug', 'show_header_footer', 'is_active', 'html', 'css', 'project_data'];

    protected $casts = [
        'show_header_footer' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION);
    }
}
