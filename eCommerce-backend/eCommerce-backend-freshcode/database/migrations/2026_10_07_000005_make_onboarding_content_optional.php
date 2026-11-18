<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->text('subtitle')->nullable()->change();
            $table->string('image_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('onboarding_pages')->whereNull('title')->update(['title' => '']);
        DB::table('onboarding_pages')->whereNull('subtitle')->update(['subtitle' => '']);
        DB::table('onboarding_pages')->whereNull('image_path')->update(['image_path' => '']);

        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
            $table->text('subtitle')->nullable(false)->change();
            $table->string('image_path')->nullable(false)->change();
        });
    }
};
