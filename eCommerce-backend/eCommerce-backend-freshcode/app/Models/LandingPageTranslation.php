<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageTranslation extends Model
{
    protected $fillable = ['landing_page_id', 'locale', 'seo_title', 'seo_description', 'seo_keywords'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'landing_page_id');
    }
}
