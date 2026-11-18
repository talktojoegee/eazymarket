<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-market Cash-on-Delivery cap.
 *
 * A flat amount stored in the platform's BASE currency (like `cod_fee` and
 * product prices). When an order's payable total exceeds this, COD is not
 * offered for that market. `0.00` means no cap (COD always allowed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->decimal('max_cod_amount', 12, 2)->default('0.00')->after('cod_fee');
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropColumn('max_cod_amount');
        });
    }
};
