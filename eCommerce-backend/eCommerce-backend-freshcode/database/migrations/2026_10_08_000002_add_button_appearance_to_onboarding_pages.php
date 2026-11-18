<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->string('next_button_background_color', 7)->default('#ffb511');
            $table->string('next_button_text_color', 7)->default('#ffffff');
            $table->boolean('show_skip')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->dropColumn(['next_button_background_color', 'next_button_text_color', 'show_skip']);
        });
    }
};
