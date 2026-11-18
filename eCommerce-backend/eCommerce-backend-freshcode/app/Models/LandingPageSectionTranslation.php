<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageSectionTranslation extends Model
{
    protected $fillable = ['landing_page_section_id', 'locale', 'content'];

    protected $casts = ['content' => 'array'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(LandingPageSection::class, 'landing_page_section_id');
    }
}
