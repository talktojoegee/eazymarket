<?php

namespace App\Models;

use App\Enums\Market\MarketStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A Market is an admin-defined storefront region.
 *
 * Carries: currency_code (presentation FX target), default language, and the
 * list of countries it covers (via market_country pivot).
 *
 * The customer's market is resolved at request time by DetectMarketMiddleware
 * using (in order): URL/header override → cookie/Hive → users.market_id →
 * IP-detected country → Setting `default_market_id`.
 *
 * @property int    $id
 * @property string $code
 * @property string $name
 * @property string $currency_code
 * @property string $default_language
 * @property bool   $is_default
 * @property string $status
 * @property int    $priority
 * @property array  $metadata
 *
 * @method static Builder active()
 * @method static Builder default()
 * @method static Builder forCountry(string $iso2)
 */
class Market extends Model
{
    use HasFactory;

    private ?int $resolvedDecimals = null;

    protected $fillable = [
        'code',
        'name',
        'currency_code',
        'default_language',
        'is_default',
        'status',
        'priority',
        'metadata',
        'symbol_position',
        'space_between_symbol',
        'thousand_separator',
        'decimal_separator',
        'grouping_style',
        'decimal_places',
        'negative_format',
        'platform_fee',
        'cod_fee',
        'max_cod_amount',
    ];

    protected $casts = [
        'is_default'           => 'boolean',
        'seller_fields_initialized' => 'boolean',
        'priority'             => 'integer',
        'metadata'             => 'array',
        'space_between_symbol' => 'boolean',
        'decimal_places'       => 'integer',
        'platform_fee'         => 'decimal:2',
        'cod_fee'              => 'decimal:2',
        'max_cod_amount'       => 'decimal:2',
    ];


    /**
     * Resolve the decimal places to render in this market.
     *
     * Markets may override the currency's default — e.g. INR-with-no-paise
     * (decimal_places = 0) even though `currencies.decimal_places` is 2.
     * Falls back to the currency's value, then to 2.
     */
    public function resolveDecimals(): int
    {
        if ($this->decimal_places !== null) {
            return (int) $this->decimal_places;
        }

        if ($this->resolvedDecimals !== null) {
            return $this->resolvedDecimals;
        }

        if ($this->relationLoaded('currency') && $this->currency) {
            return $this->resolvedDecimals = (int) $this->currency->decimal_places;
        }

        $fromDb = DB::table('currencies')
            ->where('code', $this->currency_code)
            ->value('decimal_places');

        return $this->resolvedDecimals = $fromDb !== null ? (int) $fromDb : 2;
    }


    /** Presentation currency for this market. */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** Countries covered by this market (ISO2 list via pivot). */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(
            Country::class,
            'market_country',
            'market_id',
            'country_iso2',
            'id',
            'iso2',
        )->withTimestamps();
    }

    /** Per-market FX rates. */
    public function rates(): HasMany
    {
        return $this->hasMany(MarketCurrencyRate::class);
    }

    /** Users enrolled in this market (via user_market pivot — currently 1:1 per user). */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_market')->withTimestamps();
    }

    /** Orders placed in this market. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Tax classes scoped to this market. */
    public function taxClasses(): HasMany
    {
        return $this->hasMany(TaxClass::class);
    }

    /** Stores enabled in this market. */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_market')->withTimestamps();
    }

    /** App-notifications targeted at this market. */
    public function appNotifications(): BelongsToMany
    {
        return $this->belongsToMany(AppNotification::class, 'app_notification_market_map')->withTimestamps();
    }


    public function scopeActive($query)
    {
        return $query->where('status', MarketStatusEnum::ACTIVE());
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Match the market that covers a given ISO2 country.
     * Ties broken by `priority` DESC.
     */
    public function scopeForCountry($query, string $iso2)
    {
        $iso2 = strtoupper($iso2);

        return $query->whereHas('countries', fn ($q) => $q->where('iso2', $iso2))
            ->orderByDesc('priority');
    }


    public function isActive(): bool
    {
        return $this->status === MarketStatusEnum::ACTIVE()->value;
    }
}
