<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of the delivery ETA for the item, taken from the matched shipping
 * zone rate at order-placement time. Kept on the item so the promised window
 * survives later rate changes. The customer-facing "arrives between X and Y"
 * date range is derived from the order date + these values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('eta_min')->nullable()->after('subtotal');
            $table->unsignedInteger('eta_max')->nullable()->after('eta_min');
            $table->string('eta_unit', 16)->nullable()->after('eta_max');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['eta_min', 'eta_max', 'eta_unit']);
        });
    }
};
