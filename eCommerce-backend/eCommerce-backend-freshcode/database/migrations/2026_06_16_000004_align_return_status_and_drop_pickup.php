<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First, drop the foreign key constraint
        Schema::table('order_item_returns', function (Blueprint $table) {
            if (Schema::hasColumn('order_item_returns', 'delivery_boy_id')) {
                // Drop foreign key constraint if it exists
                $table->dropForeign(['delivery_boy_id']);
            }
        });

        // Then modify the enum column
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE order_item_returns MODIFY return_status ENUM('requested','approved','declined','in_transit','received','refunded','closed','cancelled') NOT NULL DEFAULT 'requested'");
        }

        // Now drop the columns
        Schema::table('order_item_returns', function (Blueprint $table) {
            $columns = ['pickup_status', 'delivery_boy_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('order_item_returns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        // Re-add columns first
        Schema::table('order_item_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('order_item_returns', 'delivery_boy_id')) {
                $table->unsignedBigInteger('delivery_boy_id')->nullable()->after('store_id');
            }
            if (!Schema::hasColumn('order_item_returns', 'pickup_status')) {
                $table->enum('pickup_status', ['pending', 'assigned', 'picked_up', 'delivered_to_seller', 'cancelled'])->default('pending')->after('store_id');
            }
        });

        // Re-add the foreign key constraint in down() method
        Schema::table('order_item_returns', function (Blueprint $table) {
            if (Schema::hasColumn('order_item_returns', 'delivery_boy_id')) {
                $table->foreign('delivery_boy_id')
                    ->references('id')
                    ->on('users') // or whatever table this references
                    ->onDelete('set null');
            }
        });

        // Revert the enum
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE order_item_returns MODIFY return_status ENUM('cancelled','requested','seller_approved','seller_rejected','pickup_assigned','picked_up','received_by_seller','refund_processed','completed') NOT NULL DEFAULT 'requested'");
        }
    }
};
