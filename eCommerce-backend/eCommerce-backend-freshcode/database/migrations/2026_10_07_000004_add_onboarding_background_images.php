<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->string('background_type', 10)->default('color')->after('background_color');
            $table->string('background_image_path')->nullable()->after('background_type');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->dropColumn(['background_type', 'background_image_path']);
        });
    }
};
