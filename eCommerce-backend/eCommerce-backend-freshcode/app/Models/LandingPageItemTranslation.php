<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageItemTranslation extends Model
{
    protected $fillable = ['landing_page_item_id', 'locale', 'content'];

    protected $casts = ['content' => 'array'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(LandingPageItem::class, 'landing_page_item_id');
    }
}
