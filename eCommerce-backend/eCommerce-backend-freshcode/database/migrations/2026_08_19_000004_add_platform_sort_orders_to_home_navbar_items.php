<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_navbar_items', function (Blueprint $table) {
            $table->unsignedInteger('app_sort_order')->default(0)->after('sort_order');
            $table->unsignedInteger('web_sort_order')->default(0)->after('app_sort_order');
            $table->index(['is_active', 'app_sort_order'], 'home_nav_items_active_app_sort_idx');
            $table->index(['is_active', 'web_sort_order'], 'home_nav_items_active_web_sort_idx');
        });

        DB::table('home_navbar_items')->orderBy('id')->each(function (object $item): void {
            DB::table('home_navbar_items')->where('id', $item->id)->update([
                'app_sort_order' => $item->sort_order,
                'web_sort_order' => $item->sort_order,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('home_navbar_items', function (Blueprint $table) {
            $table->dropIndex('home_nav_items_active_app_sort_idx');
            $table->dropIndex('home_nav_items_active_web_sort_idx');
            $table->dropColumn(['app_sort_order', 'web_sort_order']);
        });
    }
};
