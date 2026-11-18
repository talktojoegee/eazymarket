<?php

namespace Database\Seeders;

use App\Enums\BrandStatusEnum;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/Data/brands_data.json');
        if (!file_exists($jsonPath)) {
            $this->command?->error("JSON file not found at: $jsonPath");
            return;
        }

        $brandsData = json_decode(file_get_contents($jsonPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command?->error("JSON parse error: " . json_last_error_msg());
            return;
        }

        DB::transaction(function () use ($brandsData) {
            foreach ($brandsData as $data) {
                // Resolve scope_id if scope_type is 'category'
                $scopeId = null;
                if ($data['scope_type'] === 'category') {
                    $category = Category::where('title', $data['category_title'])->first();
                    if (!$category) {
                        $this->command?->warn("Category '{$data['category_title']}' not found – skipping brand '{$data['title']}'.");
                        continue;
                    }
                    $scopeId = $category->id;
                }

                // Create or update brand
                Brand::updateOrCreate(
                    [
                        'title' => $data['title'],
                        'scope_type' => $data['scope_type'],
                        'scope_id' => $scopeId,
                    ],
                    [
                        'description' => $data['description'],
                        'status' => BrandStatusEnum::ACTIVE(), // or from JSON if dynamic
                        'metadata' => [],
                    ]
                );
            }
        });

        $this->command?->info('Brands seeded successfully from JSON.');
    }
}
