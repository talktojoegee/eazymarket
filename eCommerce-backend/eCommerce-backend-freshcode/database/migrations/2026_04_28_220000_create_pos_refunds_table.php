<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('refunded_by_user_id')->nullable();
            $table->decimal('total_amount', 12, 2)->default('0.00');
            $table->string('refund_method', 32)->default('cash');
            $table->json('refund_method_meta')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'created_at'], 'pos_refunds_store_id_created_at_index');
            $table->index('order_id', 'pos_refunds_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_refunds');
    }
};
