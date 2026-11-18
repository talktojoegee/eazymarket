<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('surface', 30);
            $table->string('code', 20);
            $table->string('name', 80);
            $table->string('native_name', 80);
            $table->enum('direction', ['ltr', 'rtl'])->default('ltr');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_recommended')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['surface', 'code']);
            $table->index(['surface', 'is_active']);
        });

        Schema::create('language_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('key', 191);
            $table->text('value');
            $table->timestamps();
            $table->unique(['language_id', 'key']);
        });

        $now = now();
        $panelLanguages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'is_default' => true, 'is_recommended' => true, 'sort_order' => 10],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'is_recommended' => true, 'sort_order' => 20],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'is_recommended' => true, 'sort_order' => 30],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'is_recommended' => true, 'sort_order' => 40],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'is_recommended' => false, 'sort_order' => 50],
            ['code' => 'hi', 'name' => 'Hindi', 'native_name' => 'हिन्दी', 'direction' => 'ltr', 'is_active' => true, 'is_default' => false, 'is_recommended' => false, 'sort_order' => 60],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'is_active' => true, 'is_default' => false, 'is_recommended' => true, 'sort_order' => 70],
        ];
        DB::table('languages')->insert(array_map(fn (array $language) => [
            ...$language,
            'surface' => 'panel',
            'created_at' => $now,
            'updated_at' => $now,
        ], $panelLanguages));

        $clientLanguages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'sort_order' => 10],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'sort_order' => 30],
            ['code' => 'hi', 'name' => 'Hindi', 'native_name' => 'हिन्दी', 'direction' => 'ltr', 'sort_order' => 60],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'sort_order' => 70],
        ];
        $rows = [];
        foreach (['web', 'customer_app', 'seller_app'] as $surface) {
            foreach ($clientLanguages as $language) {
                $rows[] = [
                    ...$language,
                    'surface' => $surface,
                    'is_active' => true,
                    'is_default' => $language['code'] === 'en',
                    'is_recommended' => in_array($language['code'], ['en', 'fr', 'ar'], true),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('languages')->insert($rows);

        Schema::table('markets', function (Blueprint $table) {
            $table->string('default_language', 20)->default('en')->change();
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->string('default_language', 5)->default('en')->change();
        });
        Schema::dropIfExists('language_translations');
        Schema::dropIfExists('languages');
    }
};
