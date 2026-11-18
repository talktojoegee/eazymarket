<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $table->boolean('show_title')->default(true)->after('title');
        });

        DB::table('home_sections')->orderBy('id')->chunkById(200, function ($sections) {
            foreach ($sections as $section) {
                $config = json_decode($section->config ?: '{}', true);
                if (! is_array($config) || ! array_key_exists('show_title', $config)) {
                    continue;
                }

                $showTitle = filter_var($config['show_title'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                unset($config['show_title']);

                DB::table('home_sections')->where('id', $section->id)->update([
                    'show_title' => $showTitle ?? true,
                    'config' => empty($config) ? null : json_encode($config),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('home_sections')->orderBy('id')->chunkById(200, function ($sections) {
            foreach ($sections as $section) {
                $config = json_decode($section->config ?: '{}', true);
                $config = is_array($config) ? $config : [];
                $config['show_title'] = (bool) $section->show_title;

                DB::table('home_sections')->where('id', $section->id)->update([
                    'config' => json_encode($config),
                ]);
            }
        });

        Schema::table('home_sections', function (Blueprint $table) {
            $table->dropColumn('show_title');
        });
    }
};
