<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('transaction_id', 191)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('payment_method', 191);
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded', 'partially_refunded'])->default('pending');
            $table->text('message')->nullable();
            $table->json('payment_details')->nullable();
            $table->timestamps();
            $table->unique('uuid', 'order_payment_transactions_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payment_transactions');
    }
};
