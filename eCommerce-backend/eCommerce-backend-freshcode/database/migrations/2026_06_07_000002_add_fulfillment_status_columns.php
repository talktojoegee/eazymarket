<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — add the fulfillment-rollup axis alongside the existing `status`
 * columns (kept separate so existing reads are untouched). Values come from
 * App\Enums\Order\FulfillmentStatusEnum and are DERIVED from shipments in
 * Phase 2. New rows default to `unfulfilled`; historical rows are left as-is
 * (new-orders-only cutover).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_status', 32)->default('unfulfilled')->after('status');
            $table->index('fulfillment_status', 'orders_fulfillment_status_index');
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->string('fulfillment_status', 32)->default('unfulfilled')->after('status');
            $table->index('fulfillment_status', 'seller_orders_fulfillment_status_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('fulfillment_status', 32)->default('unfulfilled')->after('status');
            $table->index('fulfillment_status', 'order_items_fulfillment_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_fulfillment_status_index');
            $table->dropColumn('fulfillment_status');
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropIndex('seller_orders_fulfillment_status_index');
            $table->dropColumn('fulfillment_status');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_fulfillment_status_index');
            $table->dropColumn('fulfillment_status');
        });
    }
};
