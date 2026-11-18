<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained('home_sections')->cascadeOnDelete();
            $table->string('purpose')->default('display'); // ItemPurposeEnum: display | filter
            $table->nullableMorphs('itemable');            // Product | Category | Brand; null = custom/banner-only
            $table->string('title')->nullable();           // per-item title override (hero image+title)
            $table->string('link_url')->nullable();        // banner custom URL (when itemable is null)
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['home_section_id', 'purpose', 'sort_order'], 'home_section_items_section_purpose_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_items');
    }
};
