<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('store_id');
            $table->integer('quantity');
            $table->char('addon_signature', 40)->nullable();
            $table->enum('save_for_later', ['0', '1'])->default('0');
            $table->timestamps();
            $table->index(['cart_id', 'product_variant_id', 'store_id', 'addon_signature'], 'cart_items_addon_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
