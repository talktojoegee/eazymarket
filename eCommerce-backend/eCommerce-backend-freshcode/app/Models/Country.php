<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @method static where(string $string, mixed $input)
 * @method static when(mixed $query, \Closure $param)
 */
class Country extends Model
{
    protected $fillable = [
        'name',
        'iso3',
        'iso2',
        'numeric_code',
        'phonecode',
        'capital',
        'currency',
        'currency_name',
        'currency_symbol',
        'tld',
        'native',
        'region',
        'subregion',
        'timezones',
        'translations',
        'latitude',
        'longitude',
        'emoji',
        'emojiU',
        'flag',
        'wikiDataId',
    ];

    protected $casts = [
        'seller_fields_initialized' => 'boolean',
        'seller_bank_fields_initialized' => 'boolean',
        'seller_business_documents_initialized' => 'boolean',
    ];

    public function dynamicSellerFields(): BelongsToMany
    {
        return $this->belongsToMany(DynamicSellerField::class, 'dynamic_seller_field_country')->withPivot('archived_at');
    }
}
