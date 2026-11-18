<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shopify-style return additions:
 *   - quantity              : units returned for the line (partial returns).
 *   - restocking_fee        : fee withheld from the refund when restocking.
 *   - return_shipping_charge : return shipping cost withheld from the refund.
 *
 * reason_type, reason_code, shipping_cost_payer, return_shipment_id and the
 * admin_adjudication_* columns already exist from earlier migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('order_item_returns', 'quantity')) {
                $table->unsignedInteger('quantity')->default(1)->after('order_item_id');
            }
            if (!Schema::hasColumn('order_item_returns', 'restocking_fee')) {
                $table->decimal('restocking_fee', 12, 2)->default(0)->after('refund_amount');
            }
            if (!Schema::hasColumn('order_item_returns', 'return_shipping_charge')) {
                $table->decimal('return_shipping_charge', 12, 2)->default(0)->after('restocking_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            foreach (['quantity', 'restocking_fee', 'return_shipping_charge'] as $column) {
                if (Schema::hasColumn('order_item_returns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
