<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referral_id');
            $table->unsignedBigInteger('beneficiary_id');
            $table->enum('beneficiary_type', ['referrer', 'referee']);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('order_amount', 12, 2);
            $table->enum('bonus_method', ['fixed', 'percentage']);
            $table->decimal('bonus_value', 10, 2);
            $table->decimal('max_cap', 10, 2)->nullable();
            $table->decimal('earned_amount', 12, 2);
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['referral_id', 'order_id'], 'referral_earnings_referral_id_order_id_index');
            $table->index(['beneficiary_id', 'status'], 'referral_earnings_beneficiary_id_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_earnings');
    }
};
