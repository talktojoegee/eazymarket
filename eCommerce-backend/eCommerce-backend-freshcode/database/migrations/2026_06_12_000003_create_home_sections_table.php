<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_layout_id')->constrained('home_layouts')->cascadeOnDelete();
            $table->string('type');               // HomeSectionTypeEnum: hero | products | categories | brands | banners
            $table->string('title')->nullable();
            $table->string('style')->nullable();  // primary visual preset, scoped to the type
            $table->json('config')->nullable();   // type-specific scalar settings
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['home_layout_id', 'sort_order'], 'home_sections_layout_sort_index');
            $table->index('type', 'home_sections_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
    }
};
