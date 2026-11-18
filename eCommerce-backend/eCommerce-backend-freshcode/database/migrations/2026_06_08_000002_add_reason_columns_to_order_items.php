<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `order_items.cancellation_reason` and `delivery_fail_reason` are referenced by
 * the OrderItem model ($fillable), the cancel / force-cancel flows, and the order
 * resources, but were never added to the table — writing a cancellation reason
 * fails with "Unknown column 'cancellation_reason'". This backfills the columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'cancellation_reason')) {
                $table->string('cancellation_reason', 500)->nullable()->after('status');
            }
            if (!Schema::hasColumn('order_items', 'delivery_fail_reason')) {
                $table->string('delivery_fail_reason', 100)->nullable()->after('cancellation_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            foreach (['cancellation_reason', 'delivery_fail_reason'] as $col) {
                if (Schema::hasColumn('order_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
