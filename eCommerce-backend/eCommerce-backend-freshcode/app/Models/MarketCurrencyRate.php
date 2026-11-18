<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-market FX rate snapshot.
 *
 * `rate` converts 1 unit of `from_currency` → `to_currency`.
 * `valid_from` / `valid_until` define the rate's validity window — older rows
 * stay in place for historical reporting; current rate has `valid_until = null`.
 *
 * @property int $id
 * @property int $market_id
 * @property string $from_currency
 * @property string $to_currency
 * @property string $rate
 * @property \Illuminate\Support\Carbon $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_until
 */
class MarketCurrencyRate extends Model
{
    protected $fillable = [
        'market_id',
        'from_currency',
        'to_currency',
        'rate',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'rate'        => 'decimal:8',
        'valid_from'  => 'date',
        'valid_until' => 'date',
    ];


    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency', 'code');
    }

    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency', 'code');
    }


    /** Currently-valid rate (no expiry, or expiry in the future). */
    public function scopeCurrent($query, ?Carbon $asOf = null)
    {
        $asOf ??= now();

        return $query->where('valid_from', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $asOf);
            });
    }

    public function scopeForMarket($query, int $marketId)
    {
        return $query->where('market_id', $marketId);
    }

    public function scopeForPair($query, string $from, string $to)
    {
        return $query->where('from_currency', $from)
            ->where('to_currency', $to);
    }
}
