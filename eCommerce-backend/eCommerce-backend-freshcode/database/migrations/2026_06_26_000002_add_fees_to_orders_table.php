<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of the additional charges applied at purchase time, stored in the
 * order's own (market) currency alongside delivery_charge. `platform_fee` is
 * charged on every order; `cod_fee` only on Cash-on-Delivery orders. Both are
 * folded into final_total / total_payable and are NON-refundable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('platform_fee', 12, 2)->default('0.00')->after('delivery_charge');
            $table->decimal('cod_fee', 12, 2)->default('0.00')->after('platform_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['platform_fee', 'cod_fee']);
        });
    }
};
