<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_addon_items', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('addon_item_id');
            $table->decimal('price', 12, 2);
            $table->decimal('cost', 12, 2)->nullable();
            $table->integer('stock')->default('0');
            $table->boolean('is_available')->default('1');
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['store_id', 'addon_item_id'], 'unique_store_addon_item');
            $table->unique('uuid', 'store_addon_items_uuid_unique');
            $table->index(['store_id', 'is_available'], 'store_addon_items_store_id_is_available_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_addon_items');
    }
};
