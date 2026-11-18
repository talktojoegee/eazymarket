<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payment_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('token', 36);
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('seller_id');
            $table->json('payload');
            $table->decimal('amount', 12, 2);
            $table->decimal('cash_portion', 12, 2)->default('0.00');
            $table->string('currency_code', 8)->default('INR');
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'cancelled'])->default('pending');
            $table->string('gateway', 32)->nullable();
            $table->string('gateway_order_id', 100)->nullable();
            $table->string('gateway_payment_id', 100)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique('token', 'pos_payment_sessions_token_unique');
            $table->index('store_id', 'pos_payment_sessions_store_id_index');
            $table->index('seller_id', 'pos_payment_sessions_seller_id_index');
            $table->index('status', 'pos_payment_sessions_status_index');
            $table->index('order_id', 'pos_payment_sessions_order_id_index');
            $table->index('expires_at', 'pos_payment_sessions_expires_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payment_sessions');
    }
};
