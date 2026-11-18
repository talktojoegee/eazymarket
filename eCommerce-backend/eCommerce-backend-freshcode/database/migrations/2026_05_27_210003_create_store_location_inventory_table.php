<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_location_inventory', function (Blueprint $table) {
            $table->unsignedBigInteger('store_location_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->boolean('is_fulfillment_location')->default('0');
            $table->boolean('tracked')->default('1');
            $table->integer('stock')->default('0');
            $table->integer('low_stock_threshold')->default('0');
            $table->integer('reserved')->default('0');
            $table->timestamp('last_restocked_at')->nullable();
            $table->timestamps();
            $table->primary(['store_location_id', 'product_variant_id']);
            $table->index('product_variant_id', 'store_location_inventory_product_variant_id_index');
            $table->index('stock', 'store_location_inventory_stock_index');
            $table->index(['product_variant_id', 'is_fulfillment_location'], 'sli_variant_fulfill_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_location_inventory');
    }
};
