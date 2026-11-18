<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_navbar_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_app')->default(true);
            $table->boolean('show_on_web')->default(true);
            $table->json('home_appearance')->nullable();
            $table->json('search_labels')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        $categories = DB::table('categories')
            ->whereNull('deleted_at')
            ->whereNull('parent_id')
            ->where('is_home_category', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($categories as $index => $category) {
            $baseSlug = Str::slug($category->title) ?: 'navbar-item-'.$category->id;
            $slug = $baseSlug;
            $suffix = 2;
            while (DB::table('home_navbar_items')->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $searchLabels = Schema::hasTable('category_search_labels')
                ? DB::table('category_search_labels')->where('category_id', $category->id)->value('search_labels')
                : null;
            $appearance = $category->home_appearance;
            if (! $appearance) {
                $backgroundType = $category->background_type === 'image'
                    ? 'image'
                    : ($category->background_type === 'color' ? 'gradient' : 'none');
                $style = [
                    'background_type' => $backgroundType,
                    'gradient_start' => $category->background_color ?: '#ffffff',
                    'gradient_end' => $category->background_color ?: '#ffffff',
                    'gradient_angle' => 90,
                    'font_color' => $category->font_color ?: '#475569',
                    'active_font_color' => '#111827',
                ];
                $appearance = json_encode(['app' => $style, 'desktop' => $style]);
            }

            $navbarId = DB::table('home_navbar_items')->insertGetId([
                'title' => $category->title,
                'slug' => $slug,
                'sort_order' => $index + 1,
                'is_active' => $category->status === 'active',
                'show_on_app' => true,
                'show_on_web' => true,
                'home_appearance' => $appearance,
                'search_labels' => $searchLabels,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('home_layouts')
                ->where('scope_type', 'category')
                ->where('scope_id', $category->id)
                ->update(['scope_type' => 'navbar', 'scope_id' => $navbarId, 'updated_at' => now()]);

            $this->moveAppearanceMedia((int) $category->id, $navbarId);
        }
    }

    private function moveAppearanceMedia(int $categoryId, int $navbarId): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $collections = [
            'category_icon' => 'home_navbar_app_icon',
            'category_active_icon' => 'home_navbar_app_active_icon',
            'category_background_image' => 'home_navbar_app_background',
            'category_desktop_icon' => 'home_navbar_desktop_icon',
            'category_desktop_active_icon' => 'home_navbar_desktop_active_icon',
            'category_desktop_background_image' => 'home_navbar_desktop_background',
        ];

        foreach ($collections as $old => $new) {
            DB::table('media')
                ->where('model_type', 'App\\Models\\Category')
                ->where('model_id', $categoryId)
                ->where('collection_name', $old)
                ->update([
                    'model_type' => 'App\\Models\\HomeNavbarItem',
                    'model_id' => $navbarId,
                    'collection_name' => $new,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $items = DB::table('home_navbar_items')->get();
        foreach ($items as $item) {
            $categoryId = DB::table('categories')->where('slug', $item->slug)->value('id');
            if (! $categoryId) {
                continue;
            }

            if (Schema::hasColumn('categories', 'is_home_category')) {
                $appearance = json_decode((string) $item->home_appearance, true) ?: [];
                $appAppearance = (array) ($appearance['app'] ?? []);
                $backgroundType = match ($appAppearance['background_type'] ?? 'none') {
                    'image', 'lottie' => 'image',
                    'gradient', 'color' => 'color',
                    default => null,
                };

                DB::table('categories')->where('id', $categoryId)->update([
                    'is_home_category' => true,
                    'sort_order' => $item->sort_order,
                    'background_type' => $backgroundType,
                    'background_color' => $backgroundType === 'color' ? ($appAppearance['gradient_start'] ?? null) : null,
                    'font_color' => $appAppearance['font_color'] ?? null,
                    'home_appearance' => $item->home_appearance,
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('category_search_labels') && $item->search_labels) {
                DB::table('category_search_labels')->updateOrInsert(
                    ['category_id' => $categoryId],
                    [
                        'search_labels' => $item->search_labels,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            DB::table('home_layouts')
                ->where('scope_type', 'navbar')
                ->where('scope_id', $item->id)
                ->update(['scope_type' => 'category', 'scope_id' => $categoryId, 'updated_at' => now()]);
            $this->restoreAppearanceMedia((int) $item->id, (int) $categoryId);
        }

        DB::table('home_layouts')->where('scope_type', 'navbar')->delete();
        if (Schema::hasTable('media')) {
            DB::table('media')->where('model_type', 'App\\Models\\HomeNavbarItem')->delete();
        }

        Schema::dropIfExists('home_navbar_items');
    }

    private function restoreAppearanceMedia(int $navbarId, int $categoryId): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $collections = [
            'home_navbar_app_icon' => 'category_icon',
            'home_navbar_app_active_icon' => 'category_active_icon',
            'home_navbar_app_background' => 'category_background_image',
            'home_navbar_desktop_icon' => 'category_desktop_icon',
            'home_navbar_desktop_active_icon' => 'category_desktop_active_icon',
            'home_navbar_desktop_background' => 'category_desktop_background_image',
        ];

        foreach ($collections as $old => $new) {
            DB::table('media')
                ->where('model_type', 'App\\Models\\HomeNavbarItem')
                ->where('model_id', $navbarId)
                ->where('collection_name', $old)
                ->update([
                    'model_type' => 'App\\Models\\Category',
                    'model_id' => $categoryId,
                    'collection_name' => $new,
                    'updated_at' => now(),
                ]);
        }
    }
};
