<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-market additional charges.
 *
 * Both are flat amounts stored in the platform's BASE currency (like product
 * prices) and converted to the shopper's market currency for display/checkout
 * via PricePresenter. `platform_fee` applies to every order; `cod_fee` applies
 * only when the chosen payment method is Cash on Delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->decimal('platform_fee', 12, 2)->default('0.00')->after('negative_format');
            $table->decimal('cod_fee', 12, 2)->default('0.00')->after('platform_fee');
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropColumn(['platform_fee', 'cod_fee']);
        });
    }
};
