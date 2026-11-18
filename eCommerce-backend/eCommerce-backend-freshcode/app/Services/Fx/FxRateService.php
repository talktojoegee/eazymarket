<?php

namespace App\Services\Fx;

use App\Models\Currency;
use App\Models\Market;
use App\Models\MarketCurrencyRate;
use App\Services\Fx\Contracts\FxRateProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Foreign-exchange orchestration.
 *
 * Two responsibilities:
 *   1) refresh() — pull today's rates from the external provider and snapshot
 *      one row per (market, base → market.currency) into market_currency_rates.
 *      Old rows stay in place (closed via valid_until) for audit / historical
 *      reporting. Driven by the fx:refresh-rates Artisan command.
 *
 *   2) convert() — runtime helper. Reads the currently-valid rate from the DB
 *      (no HTTP at request time). Cached in-memory per request.
 *
 * Design choices worth knowing:
 *   - Base currency = Currency::is_base = true. Single row, single source of truth.
 *   - Markets whose currency equals the base get a 1.0 rate row so callers don't
 *     have to special-case the base market.
 *   - Frankfurter doesn't cover every ISO-4217 code; for unsupported markets we
 *     log a warning and leave the prior rate in place (don't wipe to NULL).
 *   - Conversions only walk through the base currency — A→B is computed as
 *     A→base then base→B. This keeps the snapshot table small and avoids the
 *     N² explosion of storing every direct pair.
 */
class FxRateService
{
    /** Per-request memoization of "current rate" lookups. */
    protected array $rateCache = [];

    public function __construct(
        protected FxRateProvider $provider,
    ) {}


    /**
     * Pull fresh rates from the provider and snapshot into market_currency_rates.
     *
     * Returns a summary the command logs:
     *   ['base' => 'USD', 'updated' => 3, 'skipped' => 1, 'missing' => ['XYZ']]
     */
    public function refresh(): array
    {
        $baseCurrency = Currency::query()->where('is_base', true)->first();
        if (! $baseCurrency) {
            throw new RuntimeException('No base currency configured (Currency.is_base).');
        }
        $base = strtoupper($baseCurrency->code);

        $marketCurrencies = Market::query()
            ->active()
            ->pluck('currency_code')
            ->map(fn ($c) => strtoupper((string) $c))
            ->unique()
            ->values()
            ->all();

        $nonBaseSymbols = array_values(array_filter($marketCurrencies, fn ($c) => $c !== $base));

        $providerRates = $nonBaseSymbols !== []
            ? $this->provider->fetchRates($base, $nonBaseSymbols)
            : [];

        $today    = Carbon::today();
        $updated  = 0;
        $skipped  = 0;
        $missing  = [];

        foreach (Market::query()->active()->get() as $market) {
            $to = strtoupper($market->currency_code);

            if ($to === $base) {
                $rate = 1.0;
            } elseif (array_key_exists($to, $providerRates)) {
                $rate = $providerRates[$to];
            } else {
                $missing[] = $to;
                Log::warning('FxRateService: provider has no rate for market currency', [
                    'provider' => $this->provider->name(),
                    'base'     => $base,
                    'to'       => $to,
                    'market'   => $market->code,
                ]);
                continue;
            }

            if ($this->snapshotIfChanged($market->id, $base, $to, $rate, $today)) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->rateCache = [];

        return [
            'base'     => $base,
            'provider' => $this->provider->name(),
            'updated'  => $updated,
            'skipped'  => $skipped,
            'missing'  => array_values(array_unique($missing)),
        ];
    }

    /**
     * Refresh the FX rate for a single market.
     *
     * Called from MarketController::store / update so a newly created or
     * currency-changed market gets a live rate immediately, without waiting
     * for the next scheduled fx:refresh-rates run.
     *
     * Returns the same summary shape as refresh() so callers can log it.
     */
    public function refreshForMarket(Market $market): array
    {
        $baseCurrency = Currency::query()->where('is_base', true)->first();
        if (! $baseCurrency) {
            throw new RuntimeException('No base currency configured (Currency.is_base).');
        }
        $base = strtoupper($baseCurrency->code);
        $to   = strtoupper($market->currency_code);

        $today   = Carbon::today();
        $missing = [];

        if ($to === $base) {
            $rate = 1.0;
        } else {
            $rates = $this->provider->fetchRates($base, [$to]);
            if (! array_key_exists($to, $rates)) {
                $missing[] = $to;
                Log::warning('FxRateService: provider has no rate for market currency', [
                    'provider' => $this->provider->name(),
                    'base'     => $base,
                    'to'       => $to,
                    'market'   => $market->code,
                ]);
                return [
                    'base'     => $base,
                    'provider' => $this->provider->name(),
                    'updated'  => 0,
                    'skipped'  => 0,
                    'missing'  => $missing,
                ];
            }
            $rate = $rates[$to];
        }

        $changed = $this->snapshotIfChanged($market->id, $base, $to, $rate, $today);

        unset($this->rateCache["{$market->id}:{$base}:{$to}"]);

        return [
            'base'     => $base,
            'provider' => $this->provider->name(),
            'updated'  => $changed ? 1 : 0,
            'skipped'  => $changed ? 0 : 1,
            'missing'  => $missing,
        ];
    }

    /**
     * Insert a new snapshot row only if the rate has actually moved.
     * Closes the previous open row (valid_until = today) before inserting.
     *
     * Returns true when a new row was written, false when the rate was unchanged.
     */
    protected function snapshotIfChanged(int $marketId, string $from, string $to, float $rate, Carbon $asOf): bool
    {
        if (!is_finite($rate) || $rate <= 0) {
            Log::warning('Rejected non-positive FX rate', [
                'market_id' => $marketId,
                'from' => $from,
                'to' => $to,
                'rate' => $rate,
            ]);

            return false;
        }

        $rateStr = number_format($rate, 8, '.', '');

        return DB::transaction(function () use ($marketId, $from, $to, $rateStr, $asOf): bool {
            /** @var MarketCurrencyRate|null $current */
            $current = MarketCurrencyRate::query()
                ->forMarket($marketId)
                ->forPair($from, $to)
                ->whereNull('valid_until')
                ->orderByDesc('valid_from')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($current && bccomp((string) $current->rate, $rateStr, 8) === 0) {
                return false;
            }

            if ($current && $current->valid_from->isSameDay($asOf)) {
                $current->update(['rate' => $rateStr]);
                Cache::forget(self::cacheKey($marketId, $from, $to));
                return true;
            }

            if ($current) {
                $current->update(['valid_until' => $asOf]);
            }

            MarketCurrencyRate::create([
                'market_id'     => $marketId,
                'from_currency' => $from,
                'to_currency'   => $to,
                'rate'          => $rateStr,
                'valid_from'    => $asOf,
                'valid_until'   => null,
            ]);

            Cache::forget(self::cacheKey($marketId, $from, $to));

            return true;
        });
    }


    /**
     * Convert `$amount` from `$from` to `$to` using the currently-valid rate
     * for `$marketId`. Triangulates via the base currency when the requested
     * pair isn't stored directly.
     *
     * Returns null when no rate is available — callers should fall back to
     * displaying the price in its native currency rather than guessing.
     */
    public function convert(float $amount, string $from, string $to, int $marketId): ?float
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $base = strtoupper((string) Cache::driver('array')->remember(
            'fx:base_currency',
            now()->addMinutes(5),
            fn () => optional(Currency::query()->where('is_base', true)->first())->code,
        ));

        if ($base === '') {
            return null;
        }

        if ($rate = $this->currentRate($marketId, $from, $to)) {
            return $amount * $rate;
        }

        if ($from !== $base && $to !== $base) {
            $toBase   = $this->currentRate($marketId, $base, $from);
            $fromBase = $this->currentRate($marketId, $base, $to);
            if ($toBase && $fromBase && $toBase > 0) {
                return $amount * ($fromBase / $toBase);
            }
        }

        if ($from === $base && $rate = $this->currentRate($marketId, $base, $to)) {
            return $amount * $rate;
        }
        if ($to === $base && $rate = $this->currentRate($marketId, $base, $from)) {
            return $rate > 0 ? $amount / $rate : null;
        }

        return null;
    }


    /**
     * Convert `$amount` from `$from` to `$to` WITHOUT a market context.
     *
     * Payment gateways charge in a fixed, admin-configured currency
     * (Razorpay→INR, Paystack/Flutterwave→their configured code, Stripe→its
     * account currency). That currency frequently does not match any storefront
     * market, while the amount we hold is denominated in either the platform
     * base currency (subscriptions, wallet top-ups) or an order's settled market
     * currency. This converts the amount the customer agreed to into the
     * currency the gateway will actually charge.
     *
     * Resolution walks through the base currency (base→X is market-independent):
     *   converted = amount * (rate(base→to) / rate(base→from))
     *
     * Each leg rate is resolved as:
     *   1. code === base                         → 1.0
     *   2. a currently-valid stored snapshot row  → use it (any market)
     *   3. a live provider fetch (briefly cached) → use it
     *
     * Returns null when a leg cannot be resolved. Callers MUST treat null as a
     * hard failure and refuse to charge rather than send a mislabeled amount.
     */
    public function convertCurrency(float $amount, string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $rateFrom = $this->baseLegRate($from);
        $rateTo   = $this->baseLegRate($to);

        if ($rateFrom === null || $rateTo === null || $rateFrom <= 0) {
            return null;
        }

        return $amount * ($rateTo / $rateFrom);
    }

    /**
     * Rate for "1 base = N $code". Reads a stored snapshot (from any market,
     * since the base→code rate is market-independent) and falls back to a
     * short-lived cached live fetch when nothing is stored yet.
     */
    protected function baseLegRate(string $code): ?float
    {
        $code = strtoupper($code);
        $base = $this->baseCurrencyCode();

        if ($base === '') {
            return null;
        }
        if ($code === $base) {
            return 1.0;
        }

        $stored = MarketCurrencyRate::query()
            ->forPair($base, $code)
            ->current()
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->value('rate');

        if ($stored !== null) {
            return (float) $stored;
        }

        return Cache::remember(
            "fx:gateway_leg:{$base}:{$code}",
            now()->addMinutes(30),
            function () use ($base, $code) {
                try {
                    $rates = $this->provider->fetchRates($base, [$code]);
                    return array_key_exists($code, $rates) ? (float) $rates[$code] : null;
                } catch (\Throwable $e) {
                    Log::warning('FxRateService::baseLegRate live fetch failed', [
                        'provider' => $this->provider->name(),
                        'base'     => $base,
                        'to'       => $code,
                        'error'    => $e->getMessage(),
                    ]);
                    return null;
                }
            }
        );
    }

    /** Resolve (and briefly cache) the platform base currency code. */
    protected function baseCurrencyCode(): string
    {
        return strtoupper((string) Cache::driver('array')->remember(
            'fx:base_currency',
            now()->addMinutes(5),
            fn () => optional(Currency::query()->where('is_base', true)->first())->code,
        ));
    }

    /** Read-through cache for the currently-valid rate. */
    protected function currentRate(int $marketId, string $from, string $to): ?float
    {
        $memoKey  = "{$marketId}:{$from}:{$to}";
        $cacheKey = self::cacheKey($marketId, $from, $to);

        if (array_key_exists($memoKey, $this->rateCache)) {
            return $this->rateCache[$memoKey];
        }

        $value = Cache::remember($cacheKey, now()->addDay(), function () use ($marketId, $from, $to) {
            $row = MarketCurrencyRate::query()
                ->forMarket($marketId)
                ->forPair($from, $to)
                ->current()
                ->orderByDesc('valid_from')
                ->orderByDesc('id')
                ->first();

            return $row ? (float) $row->rate : null;
        });

        return $this->rateCache[$memoKey] = $value;
    }

    /** Canonical cache key for a (market, from, to) rate lookup. */
    protected static function cacheKey(int $marketId, string $from, string $to): string
    {
        return "fx:rate:{$marketId}:" . strtoupper($from) . ':' . strtoupper($to);
    }
}
