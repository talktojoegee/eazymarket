<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->string('font_color', 7)->default('#172033')->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->dropColumn('font_color');
        });
    }
};
