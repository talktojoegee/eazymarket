<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('splash_screens', function (Blueprint $table) {
            $table->id();
            $table->string('surface', 20);
            $table->string('slot', 10);
            $table->date('scheduled_date')->nullable();
            $table->string('background_type', 10);
            $table->string('background_color', 7)->nullable();
            $table->string('background_image_path')->nullable();
            $table->string('background_video_path')->nullable();
            $table->boolean('overlay_enabled')->default(false);
            $table->string('overlay_type', 10)->nullable();
            $table->string('overlay_image_path')->nullable();
            $table->string('overlay_lottie_path')->nullable();
            $table->unsignedTinyInteger('overlay_horizontal_position')->default(50);
            $table->unsignedTinyInteger('overlay_vertical_position')->default(50);
            $table->unsignedTinyInteger('overlay_size')->default(35);
            $table->unsignedSmallInteger('min_display_seconds')->default(3);
            $table->timestamps();
            $table->unique(['surface', 'slot']);
            $table->index(['slot', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('splash_screens');
    }
};
