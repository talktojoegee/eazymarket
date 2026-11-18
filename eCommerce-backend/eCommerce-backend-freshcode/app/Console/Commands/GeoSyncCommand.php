<?php

namespace App\Console\Commands;

use App\Models\GeoCity;
use App\Models\GeoState;
use App\Services\GeoImportService;
use Illuminate\Console\Command;

class GeoSyncCommand extends Command
{
    protected $signature = 'geo:sync
        {--country= : Import a single country by iso2 (e.g. IN)}
        {--fresh : Delete and re-import the target countries}
        {--all : Import the entire bundled dataset (all countries)}';

    protected $description = 'Load states/cities for market countries from the bundled geo dataset';

    public function handle(GeoImportService $geo): int
    {
        if ($this->option('all')) {
            $iso2s = $this->allBundledIso2s($geo);
            $this->info('Importing the full dataset (' . count($iso2s) . ' countries)…');
        } elseif ($country = $this->option('country')) {
            $iso2s = [strtoupper($country)];
        } else {
            $iso2s = $geo->marketCountryIso2s();
        }

        if (empty($iso2s)) {
            $this->warn('No countries to import (no markets configured yet).');

            return self::SUCCESS;
        }

        $imported = $this->option('fresh')
            ? $geo->reimportCountries($iso2s)
            : $geo->ensureCountries($iso2s);

        $this->info(sprintf(
            'Geo sync done. Imported: %s. States: %d, Cities: %d, Pincodes: %d.',
            $imported ? implode(', ', $imported) : 'none (already present)',
            GeoState::count(),
            GeoCity::count(),
            \App\Models\GeoPincode::count(),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function allBundledIso2s(GeoImportService $geo): array
    {
        $method = new \ReflectionMethod($geo, 'streamRows');
        $method->setAccessible(true);

        $iso2s = [];
        $method->invoke($geo, 'data/geo/geo_states.ndjson.gz', function (array $r) use (&$iso2s) {
            if (isset($r['c'])) {
                $iso2s[$r['c']] = true;
            }
        });

        return array_keys($iso2s);
    }
}
