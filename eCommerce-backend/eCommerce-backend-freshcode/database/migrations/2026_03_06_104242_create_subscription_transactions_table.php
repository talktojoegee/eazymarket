<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('seller_subscription_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('payment_gateway', 191)->nullable();
            $table->string('transaction_id', 191)->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->timestamps();
            $table->unique('uuid', 'subscription_transactions_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');
    }
};
