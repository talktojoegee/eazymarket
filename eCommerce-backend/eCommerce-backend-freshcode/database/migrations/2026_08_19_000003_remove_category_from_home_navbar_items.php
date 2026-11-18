<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('home_navbar_items', 'category_id')) {
            Schema::table('home_navbar_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }

        Schema::dropIfExists('category_search_labels');

        if (Schema::hasTable('media')) {
            DB::table('media')
                ->where('model_type', 'App\\Models\\Category')
                ->whereIn('collection_name', [
                    'category_icon',
                    'category_active_icon',
                    'category_background_image',
                    'category_desktop_icon',
                    'category_desktop_active_icon',
                    'category_desktop_background_image',
                ])
                ->delete();
        }

        $columns = collect([
            'is_home_category',
            'background_type',
            'background_color',
            'font_color',
            'home_appearance',
        ])->filter(fn (string $column) => Schema::hasColumn('categories', $column))->all();

        if ($columns !== []) {
            Schema::table('categories', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'is_home_category')) {
                $table->boolean('is_home_category')->default(false);
            }
            if (! Schema::hasColumn('categories', 'background_type')) {
                $table->enum('background_type', ['image', 'color'])->nullable();
            }
            if (! Schema::hasColumn('categories', 'background_color')) {
                $table->string('background_color', 7)->nullable();
            }
            if (! Schema::hasColumn('categories', 'font_color')) {
                $table->string('font_color', 191)->nullable();
            }
            if (! Schema::hasColumn('categories', 'home_appearance')) {
                $table->json('home_appearance')->nullable();
            }
        });

        if (! Schema::hasTable('category_search_labels')) {
            Schema::create('category_search_labels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->json('search_labels')->nullable();
                $table->timestamps();
                $table->unique('category_id', 'category_search_labels_category_id_unique');
            });
        }

        if (! Schema::hasColumn('home_navbar_items', 'category_id')) {
            Schema::table('home_navbar_items', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            });
        }
    }
};
