<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_order_promo_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_order_id');
            $table->unsignedBigInteger('promo_id');
            $table->decimal('seller_order_gross_snapshot', 12, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->enum('funded_by', ['PLATFORM', 'PRORATED_SELLERS'])->default('PLATFORM');
            $table->timestamps();
            $table->unique(['seller_order_id', 'promo_id'], 'seller_order_promo_unique');
            $table->index('promo_id', 'seller_order_promo_allocations_promo_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_order_promo_allocations');
    }
};
