<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restock visibility on cancelled / returned lines.
 *
 * Until now, whether a cancelled order item was restocked into store inventory
 * lived only in the stock-movement log — there was no flag on the order item
 * itself, so the admin cancel screen and reports couldn't show "restocked yes/
 * no" without a join. This adds `order_items.is_restocked`:
 *
 *   - set TRUE when admin cancels with the restock toggle on
 *     (OrderService::cancelOrderLinesByAdmin)
 *   - set TRUE by the stock listener whenever it actually moves units back
 *     (UpdateStockOnOrderStatusChange — covers seller cancel, returns,
 *     refunds, and rejected lines)
 *   - left FALSE when the admin chose not to restock, so the line stays
 *     attributable
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'is_restocked')) {
                $table->boolean('is_restocked')
                    ->default(false)
                    ->after('fulfillment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'is_restocked')) {
                $table->dropColumn('is_restocked');
            }
        });
    }
};
