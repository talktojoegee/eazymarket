<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\Market;
use App\Services\Fx\FxRateService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Single chokepoint for "how much, in which currency, do we actually charge
 * through a payment gateway".
 *
 * Payment gateways charge in a fixed currency that usually differs from the
 * currency the amount is held in:
 *   - subscriptions / wallet top-ups → amount is in the platform BASE currency
 *   - order payments                 → amount is in the order's settled MARKET
 *                                       currency ($order->currency_code)
 *
 * This class does two things, in one place, so no gateway controller has to
 * special-case currencies again:
 *   1. Converts the source amount into the gateway's currency via
 *      FxRateService::convertCurrency().
 *   2. Expresses it in the gateway's minor unit using the currency's REAL
 *      decimal count (currencies.decimal_places) — so a zero-decimal currency
 *      like JPY/KRW is not multiplied by 100 by mistake, and a 2-decimal
 *      currency still is.
 *
 * @see \App\Services\Fx\FxRateService::convertCurrency()
 */
class GatewayAmount
{
    /**
     * Resolve the charge for a gateway.
     *
     * @param  float  $amount        Amount in the source currency.
     * @param  string $fromCurrency  Currency the $amount is denominated in.
     * @param  string $toCurrency    Currency the gateway will charge in.
     * @return array{amount: float, minor: int, currency: string, decimals: int}
     *         amount   — converted, rounded to the target currency's decimals
     *         minor    — integer minor units (amount in the gateway's smallest unit)
     *         currency — upper-cased target currency code
     *         decimals — target currency decimal places
     *
     * @throws RuntimeException when no FX rate is available to reach $toCurrency.
     */
    public static function resolve(float $amount, string $fromCurrency, string $toCurrency): array
    {
        $from = strtoupper(trim($fromCurrency));
        $to   = strtoupper(trim($toCurrency));

        if ($from === '' || $to === '') {
            throw new RuntimeException('Gateway currency conversion requires both a source and a target currency.');
        }

        if ($from === $to) {
            $converted = $amount;
        } else {
            $converted = app(FxRateService::class)->convertCurrency($amount, $from, $to);
            if ($converted === null) {
                throw new RuntimeException("No exchange rate available to convert {$from} to {$to}.");
            }
        }

        $decimals = self::decimalsFor($to);
        $rounded  = round($converted, $decimals);
        $minor    = (int) round($rounded * (10 ** $decimals));

        return [
            'amount'   => $rounded,
            'minor'    => $minor,
            'currency' => $to,
            'decimals' => $decimals,
        ];
    }

    /**
     * Express a major-unit amount in a currency's minor units using its real
     * decimal count (so JPY/KRW are not multiplied by 100 by mistake).
     */
    public static function toMinor(float $amount, string $currency): int
    {
        $decimals = self::decimalsFor(strtoupper(trim($currency)));

        return (int) round($amount * (10 ** $decimals));
    }

    /**
     * Defence-in-depth check: does an actually-paid gateway amount cover the
     * amount the order requires? The order amount ($expectedAmount, in
     * $expectedCurrency) is converted into the currency the gateway charged in
     * and compared against the paid minor units, allowing a small rounding
     * tolerance. Returns true (does not block capture) when no FX rate is
     * available — the primary control is server-side intent pricing; this is a
     * secondary net that must not fail legitimate captures on infra gaps.
     */
    public static function covers(float $expectedAmount, string $expectedCurrency, int $paidMinor, string $paidCurrency, int $toleranceMinor = 1): bool
    {
        try {
            $expected = self::resolve($expectedAmount, $expectedCurrency, $paidCurrency);
        } catch (RuntimeException $e) {
            return true;
        }

        return $paidMinor >= ($expected['minor'] - $toleranceMinor);
    }

    /** The platform base currency code (source for subscriptions & wallet). */
    public static function baseCurrency(): string
    {
        return strtoupper((string) Cache::driver('array')->remember(
            'fx:base_code',
            now()->addMinutes(5),
            fn () => optional(Currency::query()->where('is_base', true)->first())->code ?? 'USD',
        )) ?: 'USD';
    }

    /**
     * Source currency for an order payment: the request's detected market
     * currency (what the customer saw and agreed to), falling back to base when
     * no market is bound to the request.
     */
    public static function orderSourceCurrency(): string
    {
        $market = request()?->attributes->get('market');
        if ($market instanceof Market && !empty($market->currency_code)) {
            return strtoupper((string) $market->currency_code);
        }

        return self::baseCurrency();
    }

    /** Decimal places for a currency code (defaults to 2 when unknown). */
    protected static function decimalsFor(string $code): int
    {
        $code = strtoupper($code);

        return (int) Cache::driver('array')->remember(
            "fx:gateway_decimals:{$code}",
            now()->addMinutes(10),
            fn () => optional(Currency::query()->find($code))->decimal_places ?? 2,
        );
    }
}
