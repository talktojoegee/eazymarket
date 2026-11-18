<?php

namespace Database\Seeders;

use App\Models\GeoCity;
use App\Models\GeoState;
use App\Services\GeoImportService;
use Illuminate\Database\Seeder;

class GeoSeeder extends Seeder
{
    public function run(): void
    {
        $geo = app(GeoImportService::class);
        $imported = $geo->ensureCountries($geo->marketCountryIso2s());

        $this->command?->info(sprintf(
            'GeoSeeder: imported [%s]. States: %d, Cities: %d.',
            $imported ? implode(', ', $imported) : 'none',
            GeoState::count(),
            GeoCity::count(),
        ));
    }
}
