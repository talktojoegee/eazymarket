<?php

namespace App\Models;

use App\Enums\Market\CurrencyStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Currency catalog.
 *
 * PK is `code` (ISO 4217, 3 chars) — not the usual auto-increment id. Markets
 * reference currencies by code; FX rates link markets ↔ currencies.
 *
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $decimal_places
 * @property string $status
 * @property bool $is_base
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(string $col, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder active()
 */
class Currency extends Model
{
    /** PK is the 3-char ISO code. */
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'status',
        'is_base',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'is_base'        => 'boolean',
    ];


    /** Markets that present prices in this currency. */
    public function markets(): HasMany
    {
        return $this->hasMany(Market::class, 'currency_code', 'code');
    }

    /** FX-rate rows where this is the source currency. */
    public function rateRowsAsFrom(): HasMany
    {
        return $this->hasMany(MarketCurrencyRate::class, 'from_currency', 'code');
    }

    /** FX-rate rows where this is the target currency. */
    public function rateRowsAsTo(): HasMany
    {
        return $this->hasMany(MarketCurrencyRate::class, 'to_currency', 'code');
    }


    public function scopeActive($query)
    {
        return $query->where('status', CurrencyStatusEnum::ACTIVE());
    }
}
