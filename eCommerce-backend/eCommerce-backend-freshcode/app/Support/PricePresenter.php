<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\Market;
use App\Services\Fx\FxRateService;
use App\Services\MarketService;
use Illuminate\Support\Facades\Cache;

/**
 * Single chokepoint for converting a base-currency amount into the customer's
 * presentation currency.
 *
 * Why a presenter and not a `convert()` call sprinkled across every resource:
 *   - One place to enforce rounding to the market currency's decimal_places.
 *   - One place to decide the fallback behaviour when no FX rate exists
 *     (we return the raw base amount rather than null — never show "—" for a
 *     price).
 *   - One shape for the API payload, so every client (web + 3 Flutter apps)
 *     parses the same fields.
 *
 * Stored prices on `product_variants.price` / `special_price` are ALWAYS in the
 * platform's base currency (`currencies.is_base = true`). Conversion happens
 * here at read time. Orders are different — they snapshot the rate at checkout
 * (see OrderService) and never call this presenter on the read path.
 */
class PricePresenter
{
    /**
     * Convert a base-currency amount for the given market.
     *
     * Returns an array shaped:
     *   [
     *     'price'           => 8324.00,           // converted, rounded
     *     'price_base'      => 100.00,            // original base amount
     *     'currency_code'   => 'INR',
     *     'currency_symbol' => '₹',
     *     'fx_rate'         => 83.24,             // 1 base = X market
     *     'formatted'       => '₹8,324.00',       // pre-formatted per market
     *     'format'          => [                  // raw rules so clients can
     *       'symbol_position'      => 'before',   // re-format computed totals
     *       'space_between_symbol' => false,      // (cart × quantity, etc.)
     *       'thousand_separator'   => ',',
     *       'decimal_separator'    => '.',
     *       'grouping_style'       => 'indian',
     *       'decimal_places'       => 2,
     *       'negative_format'      => '-{n}',
     *     ],
     *   ]
     */
    public static function forMarket(float $baseAmount, Market $market): array
    {
        $baseCode = self::baseCode();
        $symbol   = self::symbolFor($market->currency_code);
        $decimals = $market->resolveDecimals();

        if ($baseCode === '' || strtoupper($baseCode) === strtoupper($market->currency_code)) {
            $amount = round($baseAmount, $decimals);
            return [
                'price'           => $amount,
                'price_base'      => $baseAmount,
                'currency_code'   => $market->currency_code,
                'currency_symbol' => $symbol,
                'fx_rate'         => 1.0,
                'formatted'       => self::format($amount, $market, $symbol, $decimals),
                'format'          => self::formatRules($market, $decimals),
            ];
        }

        $converted = app(FxRateService::class)->convertCurrency(
            $baseAmount,
            $baseCode,
            $market->currency_code,
        );

        $usedFallback = $converted === null;
        $finalAmount  = $usedFallback ? $baseAmount : $converted;
        $rate         = $usedFallback
            ? null
            : ($baseAmount <= 0 ? 1.0 : $finalAmount / $baseAmount);

        $displayCode   = $usedFallback ? $baseCode : $market->currency_code;
        $displaySymbol = $usedFallback ? self::symbolFor($baseCode) : $symbol;
        $rounded       = round($finalAmount, $decimals);

        return [
            'price'           => $rounded,
            'price_base'      => $baseAmount,
            'currency_code'   => $displayCode,
            'currency_symbol' => $displaySymbol,
            'fx_rate'         => $rate,
            'fx_fallback'     => $usedFallback,
            'formatted'       => self::format($rounded, $market, $displaySymbol, $decimals),
            'format'          => self::formatRules($market, $decimals),
        ];
    }

    /** Convenience: convert for the request's current market. */
    public static function forCurrent(float $baseAmount): array
    {
        return self::forMarket($baseAmount, app(MarketService::class)->current());
    }

    /**
     * Build a compact currency descriptor for a market — code, symbol and the
     * per-market number-formatting rules. Used by read-path consumers (order
     * resources, datatable rows, blade views) that already hold an amount in
     * the order's settled currency and only need to FORMAT it, not convert it.
     *
     * Returns null when no market is available so callers can fall back to the
     * platform default symbol.
     *
     * @return array{code:string,symbol:string,format:array<string,mixed>}|null
     */
    public static function payload(?Market $market): ?array
    {
        if (! $market) {
            return null;
        }

        $symbol   = self::symbolFor($market->currency_code);
        $decimals = $market->resolveDecimals();

        return [
            'code'   => $market->currency_code,
            'symbol' => $symbol,
            'format' => self::formatRules($market, $decimals),
        ];
    }


    public static function baseCode(): string
    {
        return (string) Cache::driver('array')->remember(
            'fx:base_code',
            now()->addMinutes(5),
            fn () => optional(Currency::query()->where('is_base', true)->first())->code ?? '',
        );
    }

    public static function symbolFor(string $code): string
    {
        $code = strtoupper($code);
        return (string) Cache::driver('array')->remember(
            "fx:symbol:{$code}",
            now()->addMinutes(5),
            fn () => optional(Currency::query()->find($code))->symbol ?? $code,
        );
    }

    protected static function decimalsFor(string $code): int
    {
        $code = strtoupper($code);
        return (int) Cache::driver('array')->remember(
            "fx:decimals:{$code}",
            now()->addMinutes(5),
            fn () => optional(Currency::query()->find($code))->decimal_places ?? 2,
        );
    }


    /**
     * Format a positive or negative amount using the market's number rules.
     *
     * Algorithm:
     *   1. Round to the resolved decimal count.
     *   2. Split into integer + fraction halves.
     *   3. Apply grouping (western 3/3/3 or indian 3/2/2) to the integer.
     *   4. Stitch with the market's decimal_separator.
     *   5. Compose with the symbol per symbol_position + space_between_symbol.
     *   6. Wrap negatives with the negative_format template.
     */
    public static function format(float $amount, Market $market, string $symbol, ?int $decimals = null): string
    {
        $decimals = $decimals ?? $market->resolveDecimals();

        return self::formatWith($amount, self::formatRules($market, $decimals), $symbol);
    }

    /**
     * Format an amount from a plain rules array (the same shape `formatRules`
     * returns) + a symbol. This is the read-path entry point: an order already
     * holds its amounts in its settled currency, so we only need the rules to
     * render them — no Market instance or FX conversion required.
     *
     * @param array<string,mixed> $rules
     */
    public static function formatWith(float $amount, array $rules, string $symbol): string
    {
        $decimals = (int) ($rules['decimal_places'] ?? 2);
        $negative = $amount < 0;
        $abs      = abs($amount);

        $rounded   = (string) number_format($abs, $decimals, '.', '');
        [$intPart, $fracPart] = $decimals > 0
            ? explode('.', $rounded, 2)
            : [$rounded, ''];

        $intGrouped = self::groupInteger(
            $intPart,
            (string) ($rules['thousand_separator'] ?? ','),
            (string) ($rules['grouping_style'] ?? 'western'),
        );

        $number = $decimals > 0
            ? $intGrouped . ($rules['decimal_separator'] ?? '.') . $fracPart
            : $intGrouped;

        $space   = ! empty($rules['space_between_symbol']) ? ' ' : '';
        $withSym = ($rules['symbol_position'] ?? 'before') === 'after'
            ? $number . $space . $symbol
            : $symbol . $space . $number;

        if (! $negative) {
            return $withSym;
        }

        $template = ($rules['negative_format'] ?? '') ?: '-{n}';
        return str_contains($template, '{n}')
            ? str_replace('{n}', $withSym, $template)
            : '-' . $withSym;
    }

    /**
     * Group an unsigned integer string per the market's grouping_style.
     *   - western: 1,234,567 (group of 3, repeating)
     *   - indian : 12,34,567 (group of 3 trailing, then groups of 2)
     */
    protected static function groupInteger(string $digits, string $separator, string $style): string
    {
        if ($separator === '' || strlen($digits) <= 3) {
            return $digits;
        }

        if ($style === 'indian') {
            $reversed = strrev($digits);
            $first    = substr($reversed, 0, 3);
            $rest     = substr($reversed, 3);
            $chunks   = $rest === '' ? [] : str_split($rest, 2);
            $parts    = array_merge([$first], $chunks);
            return strrev(implode($separator, $parts));
        }

        return number_format((float) $digits, 0, '.', $separator);
    }

    /** The format rules dictionary surfaced in the API payload. */
    public static function formatRules(Market $market, int $decimals): array
    {
        return [
            'symbol_position'      => $market->symbol_position ?? 'before',
            'space_between_symbol' => (bool) ($market->space_between_symbol ?? false),
            'thousand_separator'   => (string) ($market->thousand_separator ?? ','),
            'decimal_separator'    => (string) ($market->decimal_separator ?? '.'),
            'grouping_style'       => $market->grouping_style ?? 'western',
            'decimal_places'       => $decimals,
            'negative_format'      => $market->negative_format ?: '-{n}',
        ];
    }
}
