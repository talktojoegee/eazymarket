<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_boy_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('delivery_boy_id');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('return_id')->nullable();
            $table->enum('assignment_type', ['delivery', 'return_pickup'])->default('delivery');
            $table->timestamp('assigned_at');
            $table->enum('status', ['assigned', 'in_progress', 'completed', 'canceled', 'dropped', 'cancelled_by_admin']);
            $table->decimal('base_fee', 10, 2)->nullable();
            $table->decimal('per_store_pickup_fee', 10, 2)->nullable();
            $table->decimal('distance_based_fee', 10, 2)->nullable();
            $table->decimal('per_order_incentive', 10, 2)->nullable();
            $table->decimal('total_earnings', 10, 2)->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'rejected']);
            $table->decimal('cod_cash_collected', 10, 2)->default('0.00');
            $table->decimal('cod_cash_submitted', 10, 2)->default('0.00');
            $table->enum('cod_submission_status', ['pending', 'submitted', 'partially_submitted'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_boy_assignments');
    }
};
