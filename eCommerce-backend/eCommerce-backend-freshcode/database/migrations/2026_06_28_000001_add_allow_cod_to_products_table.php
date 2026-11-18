<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product-level Cash-on-Delivery switch.
 *
 * When a seller turns this off for a product, Cash on Delivery is not offered
 * for any cart that contains it. Defaults to '1' (allowed) so existing products
 * keep their current behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('allow_cod', ['0', '1'])->default('1')->after('is_attachment_required');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('allow_cod');
        });
    }
};
