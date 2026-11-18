<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MarketSeeder::class,
            SellerStoreSeeder::class,
            CategoriesSeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
            HomeLayoutSeeder::class,
        ]);
    }
}
