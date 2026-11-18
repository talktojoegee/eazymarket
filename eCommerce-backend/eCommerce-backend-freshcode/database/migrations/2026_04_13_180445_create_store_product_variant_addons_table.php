<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_product_variant_addons', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('addon_group_id');
            $table->unsignedBigInteger('addon_item_id');
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['store_id', 'product_variant_id', 'addon_group_id', 'addon_item_id'], 'unique_store_variant_addon');
            $table->unique('uuid', 'store_product_variant_addons_uuid_unique');
            $table->index(['store_id', 'product_variant_id'], 'store_product_variant_addons_store_id_product_variant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_variant_addons');
    }
};
