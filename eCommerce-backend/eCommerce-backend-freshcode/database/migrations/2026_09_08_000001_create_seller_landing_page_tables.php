<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropLandingPageTables();

        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('landing_page_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_id');
            $table->foreign('landing_page_id', 'lp_trans_page_fk')->references('id')->on('landing_pages')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->timestamps();
            $table->unique(['landing_page_id', 'locale'], 'landing_page_translation_unique');
        });

        Schema::create('landing_page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_id');
            $table->foreign('landing_page_id', 'lp_sections_page_fk')->references('id')->on('landing_pages')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('type', 40);
            $table->string('variant', 80);
            $table->string('preset', 120)->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['landing_page_id', 'key'], 'landing_page_section_unique');
            $table->index(['landing_page_id', 'sort_order'], 'landing_page_section_order_index');
        });

        Schema::create('landing_page_section_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_section_id');
            $table->foreign('landing_page_section_id', 'lp_section_trans_section_fk')->references('id')->on('landing_page_sections')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->json('content');
            $table->timestamps();
            $table->unique(['landing_page_section_id', 'locale'], 'landing_page_section_translation_unique');
        });

        Schema::create('landing_page_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_section_id');
            $table->foreign('landing_page_section_id', 'lp_items_section_fk')->references('id')->on('landing_page_sections')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('preset', 120)->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['landing_page_section_id', 'key'], 'landing_page_item_unique');
            $table->index(['landing_page_section_id', 'sort_order'], 'landing_page_item_order_index');
        });

        Schema::create('landing_page_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_item_id');
            $table->foreign('landing_page_item_id', 'lp_item_trans_item_fk')->references('id')->on('landing_page_items')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->json('content');
            $table->timestamps();
            $table->unique(['landing_page_item_id', 'locale'], 'landing_page_item_translation_unique');
        });

        Schema::create('landing_page_media_slots', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 80);
            $table->unsignedBigInteger('owner_id');
            $table->string('slot', 50);
            $table->boolean('use_default')->default(true);
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'slot'], 'landing_page_media_slot_unique');
            $table->index(['owner_type', 'owner_id'], 'landing_page_media_owner_index');
        });

        DB::table('settings')->where('variable', 'seller_landing')->delete();
    }

    public function down(): void
    {
        $this->dropLandingPageTables();
    }

    private function dropLandingPageTables(): void
    {
        Schema::dropIfExists('landing_page_media_slots');
        Schema::dropIfExists('landing_page_item_translations');
        Schema::dropIfExists('landing_page_items');
        Schema::dropIfExists('landing_page_section_translations');
        Schema::dropIfExists('landing_page_sections');
        Schema::dropIfExists('landing_page_translations');
        Schema::dropIfExists('landing_pages');
    }
};
