<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public const INSTALLATION_SEEDERS = [
        CountriesSeeder::class,
        CurrenciesSeeder::class,             // derives from CountriesSeeder data; must run after it
        MarketSeeder::class,
        GeoSeeder::class,                    // states/cities for market countries; must run after MarketSeeder
        DefaultRolesSeeder::class,
        BackfillWalletTypesSeeder::class,
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        // use this in fresh install
        $this->call(self::INSTALLATION_SEEDERS);
    }
}
