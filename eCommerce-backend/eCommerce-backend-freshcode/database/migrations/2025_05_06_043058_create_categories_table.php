<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('title', 191);
            $table->string('slug', 191);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('requires_approval')->default('0');
            $table->integer('sort_order')->default('0');
            $table->boolean('is_home_category')->default('0');
            $table->decimal('commission', 5, 2)->default('0.00');
            $table->enum('background_type', ['image', 'color'])->nullable();
            $table->string('background_color', 7)->nullable();
            $table->string('font_color', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique('uuid', 'categories_uuid_unique');
            $table->unique('slug', 'categories_slug_unique');
            $table->index('sort_order', 'categories_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
