<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the platform dimension (app | web). Existing rows default to 'app'
        // so the mobile layouts keep working untouched.
        Schema::table('home_layouts', function (Blueprint $table) {
            $table->string('platform')->default('app')->after('id'); // HomePagePlatformEnum
        });

        // A layout is unique per (platform, scope_type, scope_id), so app and web
        // can each have their own global + per-category layouts.
        Schema::table('home_layouts', function (Blueprint $table) {
            $table->dropUnique('home_layouts_scope_unique');
            $table->unique(['platform', 'scope_type', 'scope_id'], 'home_layouts_platform_scope_unique');
            $table->index('platform', 'home_layouts_platform_index');
        });
    }

    public function down(): void
    {
        Schema::table('home_layouts', function (Blueprint $table) {
            $table->dropUnique('home_layouts_platform_scope_unique');
            $table->dropIndex('home_layouts_platform_index');
            $table->unique(['scope_type', 'scope_id'], 'home_layouts_scope_unique');
            $table->dropColumn('platform');
        });
    }
};
