<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_pending_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('seller_order_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->char('currency_code', 3)->default('USD');
            $table->enum('reason', ['shipping_clawback', 'cancellation_fee', 'other'])->default('shipping_clawback');
            $table->text('reason_note')->nullable();
            $table->enum('status', ['pending', 'applied', 'expired', 'waived'])->default('pending');
            $table->unsignedBigInteger('applied_to_order_id')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status'], 'customer_pending_charges_user_id_status_index');
            $table->index('expires_at', 'customer_pending_charges_expires_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_pending_charges');
    }
};
