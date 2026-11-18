<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment axis additive cutover — add Shopify financial_status values
 * (authorized, paid, partially_paid, voided) alongside the legacy set on
 * order_payment_transactions.payment_status. 'completed' is kept as the legacy
 * synonym for 'paid' (matches PaymentStatusEnum). Zero orders → no data to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payment_transactions', function (Blueprint $table) {
            $table->enum('payment_status', [
                'pending',
                'authorized',
                'paid',
                'partially_paid',
                'completed',
                'partially_refunded',
                'refunded',
                'voided',
                'failed'
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_payment_transactions', function (Blueprint $table) {
            $table->enum('payment_status', [
                'pending',
                'completed',
                'failed',
                'refunded',
                'partially_refunded'
            ])->default('pending')->change();
        });
    }
};
