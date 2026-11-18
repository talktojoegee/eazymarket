<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Derives the `currencies` table from the existing `countries` data.
 *
 * Countries carry `currency` (ISO 4217 code), `currency_name`, `currency_symbol`.
 * Many countries share a currency (EUR is used by ~20 countries; USD by ~10),
 * so we SELECT DISTINCT on the code to get one row per currency.
 *
 * Decimal places aren't on `countries`, so we patch them in from a small
 * lookup of ISO 4217 currencies that aren't 2-decimal:
 *   - Zero-decimal: JPY, KRW, VND, CLP, ISK, UGX, RWF, GNF, KMF, PYG, BIF,
 *                   DJF, XAF, XOF, XPF
 *   - Three-decimal: BHD, IQD, JOD, KWD, LYD, OMR, TND
 *   - Everything else defaults to 2.
 *
 * Idempotent — safe to re-run.
 */
class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = DB::table('countries')
            ->select('currency as code', 'currency_name as name', 'currency_symbol as symbol')
            ->whereNotNull('currency')
            ->where('currency', '!=', '')
            ->orderBy('currency')
            ->get()
            ->unique('code')
            ->values();

        if ($rows->isEmpty()) {
            $this->command?->warn(
                'CurrenciesSeeder: countries table is empty — run CountriesSeeder first.'
            );
            return;
        }

        foreach ($rows as $r) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $r->code],
                [
                    'name'           => $r->name ?: $r->code,
                    'symbol'         => $r->symbol ?: $r->code,
                    'decimal_places' => $this->decimalsFor($r->code),
                    'status'         => 'active',
                    'is_base'        => $r->code === 'USD',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ],
            );
        }

        $this->command?->info('CurrenciesSeeder: seeded ' . $rows->count() . ' currencies.');
    }

    /**
     * ISO 4217 minor-unit override table for currencies that aren't 2-decimal.
     * Everything not listed here defaults to 2.
     */
    private function decimalsFor(string $code): int
    {
        return match ($code) {
            // Zero-decimal currencies (no minor unit)
            'JPY', 'KRW', 'VND', 'CLP', 'ISK',
            'UGX', 'RWF', 'GNF', 'KMF', 'PYG',
            'BIF', 'DJF', 'XAF', 'XOF', 'XPF' => 0,

            // Three-decimal currencies (Middle East mils)
            'BHD', 'IQD', 'JOD', 'KWD', 'LYD',
            'OMR', 'TND' => 3,

            default => 2,
        };
    }
}
