<?php

namespace App\Services;

use App\Models\GeoCity;
use App\Models\GeoPincode;
use App\Models\GeoState;
use App\Support\DatabaseSchema;
use Illuminate\Support\Facades\DB;

/**
 * Loads states + cities from the bundled dr5hn snapshot
 * (database/data/geo/*.json.gz) into geo_states / geo_cities.
 *
 * The bundled files hold the whole world, but we import ONLY the countries a
 * market actually covers — seller shipping-zone countries are constrained to
 * Store::allowedShippingCountries (market countries), so geo is never needed for
 * anything else. This keeps the tables to a few thousand rows instead of ~150k.
 */
class GeoImportService
{
    protected const STATES_FILE = 'data/geo/geo_states.ndjson.gz';
    protected const CITIES_FILE = 'data/geo/geo_cities.ndjson.gz';
    protected const PINCODES_FILE = 'data/geo/geo_pincodes.ndjson.gz';

    /**
     * Ensure geo rows exist for the given countries. Countries already present
     * are skipped, so this is cheap to call repeatedly (e.g. on every market
     * save). Returns the iso2 codes that were freshly imported.
     *
     * @param  array<int, string>  $iso2s
     * @return array<int, string>
     */
    public function ensureCountries(array $iso2s): array
    {
        $wanted = $this->normalizeIso2s($iso2s);
        if (empty($wanted)) {
            return [];
        }

        $existing = GeoState::whereIn('country_iso2', $wanted)
            ->distinct()
            ->pluck('country_iso2')
            ->all();

        $missing = array_values(array_diff($wanted, $existing));
        if (empty($missing)) {
            return [];
        }

        $this->importCountries($missing);

        return $missing;
    }

    /**
     * Force a re-import of the given countries (delete then insert). Used by the
     * `geo:sync --fresh` path.
     *
     * @param  array<int, string>  $iso2s
     */
    public function reimportCountries(array $iso2s): array
    {
        $wanted = $this->normalizeIso2s($iso2s);
        if (empty($wanted)) {
            return [];
        }

        GeoState::whereIn('country_iso2', $wanted)->delete();
        GeoCity::whereIn('country_iso2', $wanted)->delete();
        \App\Models\GeoPincode::whereIn('country_iso2', $wanted)->delete();
        $this->importCountries($wanted);

        return $wanted;
    }

    /**
     * Backfill pincodes for countries that already have geo (states/cities) but
     * no pincode rows yet — the case when the pincode dataset is added after
     * geo was first imported. Idempotent; skips countries already covered.
     *
     * @param  array<int, string>  $iso2s
     * @return array<int, string>
     */
    public function ensurePincodes(array $iso2s): array
    {
        $wanted = $this->normalizeIso2s($iso2s);
        if (empty($wanted)) {
            return [];
        }

        $existing = GeoPincode::whereIn('country_iso2', $wanted)
            ->distinct()
            ->pluck('country_iso2')
            ->all();

        $missing = array_values(array_diff($wanted, $existing));
        if (empty($missing)) {
            return [];
        }

        $this->bufferedInsertPincodes(array_flip($missing), now());

        return $missing;
    }

    /**
     * Distinct iso2 codes covered by any market.
     *
     * @return array<int, string>
     */
    public function marketCountryIso2s(): array
    {
        return DB::table('market_country')
            ->distinct()
            ->pluck('country_iso2')
            ->map(fn ($c) => strtoupper((string) $c))
            ->all();
    }

    /**
     * @param  array<int, string>  $iso2s
     */
    protected function importCountries(array $iso2s): void
    {
        $wanted = array_flip($iso2s);
        $now = now();

        $this->bufferedInsert('geo_states', self::STATES_FILE, $wanted, $now);
        $this->bufferedInsert('geo_cities', self::CITIES_FILE, $wanted, $now);
        $this->bufferedInsertPincodes($wanted, $now);
    }

    /**
     * Pincode rows carry more columns than the {c,s,n} geo files, so they get
     * their own streamed buffered insert. Missing pincode data for a country is
     * fine — validation treats such countries as uncovered.
     *
     * @param  array<string, int>  $wanted
     */
    protected function bufferedInsertPincodes(array $wanted, $now): void
    {
        if (!app(DatabaseSchema::class)->hasTable('geo_pincodes') || !is_file(database_path(self::PINCODES_FILE))) {
            return;
        }

        $buffer = [];
        $flush = function () use (&$buffer) {
            if ($buffer) {
                DB::table('geo_pincodes')->insert($buffer);
                $buffer = [];
            }
        };

        $this->streamRows(self::PINCODES_FILE, function (array $r) use (&$buffer, $wanted, $now, $flush) {
            if (!isset($wanted[$r['c'] ?? ''])) {
                return;
            }
            $buffer[] = [
                'country_iso2' => $r['c'],
                'postcode'     => $r['p'] ?? '',
                'city'         => $r['city'] ?? '',
                'state_code'   => ($r['sc'] ?? '') !== '' ? $r['sc'] : null,
                'state_name'   => ($r['sn'] ?? '') !== '' ? $r['sn'] : null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
            if (count($buffer) >= 2000) {
                $flush();
            }
        });

        $flush();
    }

    /**
     * Stream the NDJSON gz row-by-row, keep only wanted countries, and insert in
     * chunks so memory stays flat regardless of dataset size.
     *
     * @param  array<string, int>  $wanted  iso2 => any (flipped for O(1) lookup)
     */
    protected function bufferedInsert(string $table, string $relativePath, array $wanted, $now): void
    {
        $buffer = [];
        $flush = function () use (&$buffer, $table) {
            if ($buffer) {
                DB::table($table)->insert($buffer);
                $buffer = [];
            }
        };

        $this->streamRows($relativePath, function (array $r) use (&$buffer, $wanted, $now, $flush) {
            if (!isset($wanted[$r['c'] ?? ''])) {
                return;
            }
            $buffer[] = [
                'country_iso2' => $r['c'],
                'state_code'   => ($r['s'] ?? '') !== '' ? $r['s'] : null,
                'name'         => $r['n'] ?? '',
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
            if (count($buffer) >= 2000) {
                $flush();
            }
        });

        $flush();
    }

    /**
     * Invoke $onRow for each decoded row of a gzipped NDJSON file, streaming so
     * the whole file is never held in memory.
     */
    protected function streamRows(string $relativePath, callable $onRow): void
    {
        $path = database_path($relativePath);
        if (!is_file($path)) {
            throw new \RuntimeException("Geo data file missing: {$path}");
        }

        $handle = gzopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open geo data file: {$path}");
        }

        try {
            while (($line = gzgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $row = json_decode($line, true);
                if (is_array($row)) {
                    $onRow($row);
                }
            }
        } finally {
            gzclose($handle);
        }
    }

    /**
     * @param  array<int, string>  $iso2s
     * @return array<int, string>
     */
    protected function normalizeIso2s(array $iso2s): array
    {
        return collect($iso2s)
            ->map(fn ($c) => strtoupper(trim((string) $c)))
            ->filter(fn ($c) => strlen($c) === 2)
            ->unique()
            ->values()
            ->all();
    }
}
