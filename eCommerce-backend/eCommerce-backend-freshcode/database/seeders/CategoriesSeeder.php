<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\HomeNavbarItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = database_path('seeders/Data/categories_data.json');
        if (! file_exists($jsonPath)) {
            $this->command?->error("JSON file not found at: $jsonPath");

            return;
        }

        $categoriesData = json_decode(file_get_contents($jsonPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command?->error('JSON parse error: '.json_last_error_msg());

            return;
        }

        foreach ($categoriesData as $index => $topCategory) {
            // Create or update top category (parent_id = null)
            $parent = Category::updateOrCreate(
                ['title' => $topCategory['title']],
                [
                    'uuid' => (string) Str::uuid(),
                    'parent_id' => null,
                    'slug' => Str::slug($topCategory['title']),
                    'description' => $topCategory['description'] ?? $topCategory['title'],
                    'status' => $topCategory['status'] ?? 'active',
                    'requires_approval' => $topCategory['requires_approval'] ?? false,
                    'metadata' => json_encode([]),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );

            HomeNavbarItem::updateOrCreate(
                ['slug' => Str::slug($topCategory['title'])],
                [
                    'title' => $topCategory['title'],
                    'sort_order' => $index + 1,
                    'app_sort_order' => $index + 1,
                    'web_sort_order' => $index + 1,
                    'is_active' => ($topCategory['status'] ?? 'active') === 'active',
                    'show_on_app' => true,
                    'show_on_web' => true,
                    'home_appearance' => [
                        'app' => $this->defaultNavbarAppearance(),
                        'desktop' => $this->defaultNavbarAppearance(),
                    ],
                    'search_labels' => [],
                ],
            );

            // Create children
            if (isset($topCategory['children'])) {
                foreach ($topCategory['children'] as $child) {
                    Category::updateOrCreate(
                        ['title' => $child['title']],
                        [
                            'uuid' => (string) Str::uuid(),
                            'parent_id' => $parent->id,
                            'slug' => Str::slug($child['title']),
                            'description' => $child['description'],
                            'status' => $child['status'] ?? 'active',
                            'requires_approval' => $child['requires_approval'] ?? false,
                            'metadata' => json_encode([]),
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]
                    );
                }
            }
        }

        $this->command?->info('Categories seeded successfully from JSON.');
    }

    private function defaultNavbarAppearance(): array
    {
        return [
            'background_type' => 'none',
            'gradient_start' => '#ffffff',
            'gradient_end' => '#ffffff',
            'gradient_angle' => 90,
            'font_color' => '#475569',
            'active_font_color' => '#111827',
        ];
    }
}
