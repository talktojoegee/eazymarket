<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->json('home_appearance')->nullable()->after('font_color');
        });

        DB::table('categories')->orderBy('id')->chunkById(200, function ($categories) {
            foreach ($categories as $category) {
                $type = $category->background_type === 'image'
                    ? 'image'
                    : ($category->background_type === 'color' ? 'gradient' : 'none');
                $color = $category->background_color ?: '#ffffff';
                $app = [
                    'background_type' => $type,
                    'gradient_start' => $color,
                    'gradient_end' => $color,
                    'gradient_angle' => 90,
                    'font_color' => $category->font_color ?: '#475569',
                    'active_font_color' => '#111827',
                ];

                DB::table('categories')->where('id', $category->id)->update([
                    'home_appearance' => json_encode(['app' => $app, 'desktop' => $app]),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('home_appearance');
        });
    }
};
