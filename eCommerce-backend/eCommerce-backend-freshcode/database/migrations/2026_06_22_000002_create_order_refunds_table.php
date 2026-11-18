<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund audit trail. Until now, refunds for cancelled lines either credited
 * the customer's wallet (seller/customer cancel flows) or were silently dropped
 * (admin cancel flow recomputed final_total without recording the money owed).
 * That made it impossible to answer "how much do we still owe this customer?"
 * or to break refunds out by method (wallet credit vs. gateway refund vs.
 * manual cash refund) — which the product roadmap will need.
 *
 * Two tables:
 *   order_refunds       — one row per refund event
 *   order_refund_items  — line breakdown (which order_items the refund covers,
 *                         and how much was attributed to each)
 *
 * `status`:
 *   owed   — recorded but not yet paid back (e.g. admin will issue via gateway)
 *   issued — money actually went back (wallet credit posted, gateway succeeded)
 *   failed — refund attempt failed; admin can retry
 *
 * `method`:
 *   wallet  — credited to customer's wallet
 *   gateway — refunded via the payment gateway (Razorpay / Stripe / etc.)
 *   manual  — cash / offline refund recorded after the fact
 *
 * Amounts are stored in the order's market currency. Conversion to the wallet's
 * base currency happens at issue time (see OrderService::issueWalletRefund).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 8)->nullable();
            $table->string('method', 16);
            $table->string('status', 16)->default('owed');
            $table->text('reason')->nullable();
            $table->foreignId('issued_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->string('gateway_reference', 128)->nullable();
            // Soft link to the wallet transaction created when method=wallet.
            // No FK so wallet refactors don't break this table.
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['method', 'status']);
        });

        Schema::create('order_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refund_items');
        Schema::dropIfExists('order_refunds');
    }
};
