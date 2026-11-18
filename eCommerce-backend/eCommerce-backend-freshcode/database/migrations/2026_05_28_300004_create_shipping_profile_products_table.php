<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_profile_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_profile_id');
            $table->unsignedBigInteger('product_id');
            $table->timestamps();
            $table->unique('product_id', 'spp_product_unique');
            $table->index('shipping_profile_id', 'spp_profile_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_profile_products');
    }
};
