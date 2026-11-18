<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_items', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('addon_group_id');
            $table->string('title', 191);
            $table->string('slug', 191);
            $table->decimal('price', 10, 2)->default('0.00');
            $table->decimal('cost', 10, 2)->nullable();
            $table->enum('indicator', ['veg', 'non_veg'])->nullable();
            $table->boolean('is_available')->default('1');
            $table->integer('sort_order')->default('0');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique('uuid', 'addon_items_uuid_unique');
            $table->unique('slug', 'addon_items_slug_unique');
            $table->index('addon_group_id', 'addon_items_addon_group_id_index');
            $table->index('status', 'addon_items_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_items');
    }
};
