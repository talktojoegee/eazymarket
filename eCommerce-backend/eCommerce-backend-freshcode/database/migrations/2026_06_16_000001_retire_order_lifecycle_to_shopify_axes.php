<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shopify-aligned cutover (data layer). The project has zero orders, so there is
 * no data to backfill — this only reshapes the schema.
 *
 *   - order_items.cancel_reason (NEW)  → cancellation is item-wise (CancelReasonEnum)
 *   - removes the seller-acceptance axis (orders / seller_orders / order_items):
 *     Shopify has no accept gate — a line is either fulfilled or cancelled
 *   - orders.status / seller_orders.status default → 'open' (OrderStatusEnum)
 *   - drops hyperlocal columns: order_items.cancelable_till / delivery_fail_reason
 *     / otp / otp_verified
 */
return new class extends Migration
{
    public function up(): void
    {
        // Item-wise cancellation reason (Shopify cancel_reason parity, per line).
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'cancel_reason')) {
                $table->string('cancel_reason', 32)->nullable()->after('cancellation_reason');
            }
        });

        // Remove the seller-acceptance axis everywhere it was added.
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'acceptance_status')) {
                $table->dropIndex('orders_acceptance_status_index');
                $table->dropColumn('acceptance_status');
            }
        });
        Schema::table('seller_orders', function (Blueprint $table) {
            if (Schema::hasColumn('seller_orders', 'acceptance_status')) {
                $table->dropIndex('seller_orders_acceptance_status_index');
                $table->dropColumn('acceptance_status');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('open')->change();
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->string('status')->default('open')->change();
        });

        // Drop hyperlocal columns from order_items.
        Schema::table('order_items', function (Blueprint $table) {
            foreach (['cancelable_till', 'delivery_fail_reason', 'otp', 'otp_verified'] as $col) {
                if (Schema::hasColumn('order_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'cancelable_till')) {
                $table->enum('cancelable_till', ['pending', 'awaiting_store_response', 'accepted', 'preparing'])->nullable();
            }
            if (! Schema::hasColumn('order_items', 'delivery_fail_reason')) {
                $table->string('delivery_fail_reason', 100)->nullable();
            }
            if (! Schema::hasColumn('order_items', 'otp')) {
                $table->string('otp', 191)->nullable();
            }
            if (! Schema::hasColumn('order_items', 'otp_verified')) {
                $table->boolean('otp_verified')->default('0');
            }
            if (Schema::hasColumn('order_items', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'acceptance_status')) {
                $table->string('acceptance_status', 32)->default('pending')->after('fulfillment_status');
                $table->index('acceptance_status', 'orders_acceptance_status_index');
            }
        });
        Schema::table('seller_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('seller_orders', 'acceptance_status')) {
                $table->string('acceptance_status', 32)->default('pending')->after('fulfillment_status');
                $table->index('acceptance_status', 'seller_orders_acceptance_status_index');
            }
        });
    }
};
