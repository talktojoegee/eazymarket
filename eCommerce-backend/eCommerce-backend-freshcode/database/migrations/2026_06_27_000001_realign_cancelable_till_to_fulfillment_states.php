<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Realign the product "cancellable until" policy to the new fulfillment-status
 * vocabulary.
 *
 * Before: `products.cancelable_till` was a MySQL enum locked to the retired
 * lifecycle values (pending / awaiting_store_response / accepted / preparing),
 * and `order_items.cancelable_till` had been dropped entirely in the Shopify-axes
 * migration. The new policy uses the pre-shipment FulfillmentStatusEnum states
 * (unfulfilled / in_progress / on_hold).
 *
 * - products.cancelable_till  : enum -> varchar(32); old values mapped to 'unfulfilled'.
 * - order_items.cancelable_till: re-added as varchar(32) (snapshot at order time).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Re-add the per-order-item snapshot so the policy at order time is preserved.
        if (! Schema::hasColumn('order_items', 'cancelable_till')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('cancelable_till', 32)->nullable()->after('is_cancelable');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'cancelable_till')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('cancelable_till');
            });
        }

        // Best-effort revert of the products column to the old enum shape.
        DB::table('products')
            ->whereNotIn('cancelable_till', ['pending', 'awaiting_store_response', 'accepted', 'preparing'])
            ->update(['cancelable_till' => null]);

        DB::statement("ALTER TABLE products MODIFY cancelable_till ENUM('pending','awaiting_store_response','accepted','preparing') NULL");
    }
};
