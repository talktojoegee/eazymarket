<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeSectionMarketOrder extends Model
{
    protected $fillable = [
        'home_section_id',
        'market_id',
        'sort_order',
    ];

    protected $casts = [
        'home_section_id' => 'integer',
        'market_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
