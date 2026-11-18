<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class DynamicSellerField extends Model
{
    protected $fillable = [
        'section',
        'key',
        'label',
        'type',
        'placeholder',
        'default_value',
        'options',
        'required',
        'system',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'system' => 'boolean',
    ];

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'dynamic_seller_field_country')->withPivot('archived_at');
    }
}
