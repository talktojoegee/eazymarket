<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('store_id');
            $table->string('title', 191);
            $table->string('variant_title', 191);
            $table->decimal('gift_card_discount', 10, 2)->default('0.00');
            $table->decimal('admin_commission_amount', 10, 2)->default('0.00');
            $table->decimal('seller_commission_amount', 10, 2)->default('0.00');
            $table->enum('commission_settled', ['0', '1'])->default('0');
            $table->boolean('return_eligible')->default('0');
            $table->date('return_deadline')->nullable();
            $table->tinyInteger('returnable_days')->default('0');
            $table->boolean('is_cancelable')->default('0');
            $table->enum('cancelable_till', ['pending', 'awaiting_store_response', 'accepted', 'preparing'])->nullable();
            $table->decimal('discounted_price', 10, 2)->default('0.00');
            $table->decimal('discount', 10, 2)->default('0.00');
            $table->decimal('promo_discount', 10, 2)->default('0.00');
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('tax_percent', 5, 2)->nullable();
            $table->string('sku', 191);
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->string('status', 191);
            $table->string('otp', 191)->nullable();
            $table->boolean('otp_verified')->default('0');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
