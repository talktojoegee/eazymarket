<?php

namespace App\Services\Fx\Providers;

use App\Services\Fx\Contracts\FxRateProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Frankfurter (frankfurter.dev) provider.
 *
 * - No API key, no rate limit, free forever.
 * - ECB daily reference rates — published ~16:00 CET on TARGET business days.
 * - Covers ~30 major currencies; an unsupported symbol returns *no row* (silently
 *   dropped by the API). FxRateService handles missing targets gracefully.
 *
 * Endpoint:
 *   GET https://api.frankfurter.dev/v2/rates?base=USD&quotes=EUR,INR
 *   [{"date":"2026-06-03","base":"USD","quote":"EUR","rate":0.85812},{"date":"2026-06-03","base":"USD","quote":"INR","rate":95.24}]
 */
class FrankfurterProvider implements FxRateProvider
{
    public function __construct(
        protected string $baseUrl = 'https://api.frankfurter.dev/v2',
        protected int    $timeoutSeconds = 8,
    ) {}

    public function name(): string
    {
        return 'frankfurter';
    }

    public function fetchRates(string $base, array $symbols): array
    {
        $base    = strtoupper($base);
        $symbols = array_values(array_unique(array_map('strtoupper', $symbols)));
        $symbols = array_values(array_filter($symbols, fn ($s) => $s !== $base));

        if ($symbols === []) {
            return [];
        }

        $response = Http::timeout($this->timeoutSeconds)
            ->retry(2, 500)
            ->acceptJson()
            ->get($this->baseUrl . '/rates', [
                'base'    => $base,
                'quotes' => implode(',', $symbols),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Frankfurter request failed: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Frankfurter response was not a JSON array');
        }

        $out = [];
        foreach ($payload as $row) {
            if (! is_array($row) || ! isset($row['quote'], $row['rate']) || ! is_numeric($row['rate'])) {
                continue;
            }
            $out[strtoupper((string) $row['quote'])] = (float) $row['rate'];
        }

        return $out;
    }
}
