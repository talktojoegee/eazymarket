<?php

namespace App\Services\Fx\Contracts;

/**
 * Contract for any external foreign-exchange rate source.
 *
 * Implementations are swappable through the container binding in
 * AppServiceProvider — keeping this surface thin (one method) means the
 * Frankfurter / ExchangeRate-API / a future provider can be dropped in
 * without touching FxRateService.
 */
interface FxRateProvider
{
    /**
     * Fetch the latest mid-market rates from `$base` to each currency in `$symbols`.
     *
     * Implementations MUST:
     *   - skip $base itself if it appears in $symbols (no 1.0 self-rate row)
     *   - throw \RuntimeException on network / parse failure so the orchestrator
     *     can fall back to the previous snapshot rather than wiping rates
     *
     * @param  string  $base       ISO-4217 base currency, e.g. 'USD'
     * @param  string[] $symbols   ISO-4217 target codes, e.g. ['EUR','INR','GBP']
     * @return array<string, float> Map of target code → rate (1 base = N target)
     */
    public function fetchRates(string $base, array $symbols): array;

    /** Stable provider identifier — written to logs / Setting rows. */
    public function name(): string;
}
