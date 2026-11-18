<?php

namespace Database\Seeders;

use App\Enums\Market\MarketStatusEnum;
use App\Models\Market;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            if (Market::forCountry('US')->exists()) {
                return;
            }

            // Default US market, presenting in USD.
            $market = Market::firstOrCreate(
                ['code' => 'US'],
                [
                    'name'             => 'United States',
                    'currency_code'    => 'USD',
                    'default_language' => 'en',
                    'is_default'       => true,
                    'status'           => MarketStatusEnum::ACTIVE(),
                    'priority'         => 0,
                    'metadata'         => [],
                ]
            );

            // Cover the US (ISO2) via the market_country pivot.
            $market->countries()->syncWithoutDetaching(['US']);
        });

        $this->command?->info('US market seed completed; existing country assignments preserved.');
    }
}
