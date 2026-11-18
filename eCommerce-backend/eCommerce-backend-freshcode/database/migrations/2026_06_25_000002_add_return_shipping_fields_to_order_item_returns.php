<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Return shipping logistics captured on the "Review return request" screen:
 * the carrier + tracking the customer uses to send the item back (or a flag
 * that no return shipping is required). Separate from return_shipping_charge
 * (the amount deducted from the refund).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('order_item_returns', 'return_carrier')) {
                $table->string('return_carrier', 120)->nullable()->after('return_shipping_charge');
            }
            if (!Schema::hasColumn('order_item_returns', 'return_tracking_number')) {
                $table->string('return_tracking_number', 120)->nullable()->after('return_carrier');
            }
            if (!Schema::hasColumn('order_item_returns', 'return_tracking_url')) {
                $table->string('return_tracking_url', 512)->nullable()->after('return_tracking_number');
            }
            if (!Schema::hasColumn('order_item_returns', 'no_shipping_required')) {
                $table->boolean('no_shipping_required')->default(false)->after('return_tracking_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            foreach (['return_carrier', 'return_tracking_number', 'return_tracking_url', 'no_shipping_required'] as $column) {
                if (Schema::hasColumn('order_item_returns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
