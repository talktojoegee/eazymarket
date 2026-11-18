<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make refund records self-describing.
 *
 * Until now, every OrderRefund row carried a single `amount` and (optionally)
 * an items pivot. Shipping refunds lived only in `notes` as free text, and a
 * new refund that fully covered a previously-OWED row was created by flipping
 * the old row to ISSUED — which silently double-counted in the `amount_refunded`
 * accessor whenever the new row also existed.
 *
 * Two columns close both gaps:
 *
 *   - `shipping_refund_amount` — how much of this refund's `amount` was for
 *     shipping / delivery charges. Lets us answer "is shipping refunded yet?"
 *     with `SUM(shipping_refund_amount) >= order.shipping_total` instead of
 *     parsing notes. Default 0 so existing rows are unaffected.
 *
 *   - `settled_by_refund_id` — when a new ISSUED refund row pays off an
 *     existing OWED row, the old row's `settled_by_refund_id` points at the
 *     new row. The old row stays for audit but is excluded from
 *     `amount_refund_owed`. No status flip, no delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            if (!Schema::hasColumn('order_refunds', 'shipping_refund_amount')) {
                $table->decimal('shipping_refund_amount', 12, 2)
                    ->default(0)
                    ->after('amount');
            }

            if (!Schema::hasColumn('order_refunds', 'settled_by_refund_id')) {
                // Self-referential FK — points to the refund that paid this
                // owed row off. Nullable; only OWED rows ever have it set.
                $table->foreignId('settled_by_refund_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('order_refunds')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            if (Schema::hasColumn('order_refunds', 'settled_by_refund_id')) {
                $table->dropForeign(['settled_by_refund_id']);
                $table->dropColumn('settled_by_refund_id');
            }
            if (Schema::hasColumn('order_refunds', 'shipping_refund_amount')) {
                $table->dropColumn('shipping_refund_amount');
            }
        });
    }
};
