<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('market_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('store_location_id')->nullable();
            $table->unsignedBigInteger('shipping_zone_id')->nullable();
            $table->enum('fulfillment_method', ['shiprocket', 'manual', 'local_delivery', 'local_pickup'])->nullable();
            $table->string('status', 32)->default('pending');
            $table->decimal('total_price', 10, 2);
            $table->decimal('gross_subtotal', 12, 2)->default('0.00');
            $table->decimal('shipping_charge_applied', 12, 2)->default('0.00');
            $table->decimal('tax_amount', 12, 2)->default('0.00');
            $table->decimal('promo_share', 12, 2)->default('0.00');
            $table->decimal('free_shipping_threshold_applied', 12, 2)->nullable();
            $table->decimal('shipping_clawback_applied', 12, 2)->default('0.00');
            $table->decimal('promo_clawback_applied', 12, 2)->default('0.00');
            $table->timestamps();
            $table->index(['market_id', 'status'], 'seller_orders_market_id_status_index');
            $table->index(['store_location_id', 'status'], 'seller_orders_store_location_id_status_index');
            $table->index('fulfillment_method', 'seller_orders_fulfillment_method_index');
            $table->index('status', 'seller_orders_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_orders');
    }
};
