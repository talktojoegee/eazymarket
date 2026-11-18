<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combo_packs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();
            $table->unsignedBigInteger('store_id');
            $table->timestamps();
            $table->foreign('product_id', 'cp_product_fk')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('store_id', 'cp_store_fk')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::create('combo_pack_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('combo_pack_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['combo_pack_id', 'product_variant_id'], 'cpi_pack_variant_uq');
            $table->index(['product_id', 'product_variant_id'], 'cpi_product_variant_idx');
            $table->foreign('combo_pack_id', 'cpi_pack_fk')->references('id')->on('combo_packs')->cascadeOnDelete();
            $table->foreign('product_id', 'cpi_product_fk')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('product_variant_id', 'cpi_variant_fk')->references('id')->on('product_variants')->restrictOnDelete();
        });

        Schema::create('order_item_combo_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->string('product_title', 191);
            $table->string('variant_title', 191);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->index(['order_item_id', 'product_variant_id'], 'oicc_order_variant_idx');
            $table->foreign('order_item_id', 'oicc_order_item_fk')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('product_id', 'oicc_product_fk')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('product_variant_id', 'oicc_variant_fk')->references('id')->on('product_variants')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE products MODIFY type ENUM('simple', 'variant', 'digital', 'kit') NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_combo_components');
        Schema::dropIfExists('combo_pack_items');
        Schema::dropIfExists('combo_packs');
        DB::table('products')->where('type', 'kit')->update(['type' => 'simple']);
        DB::statement("ALTER TABLE products MODIFY type ENUM('simple', 'variant', 'digital') NOT NULL");
    }
};
