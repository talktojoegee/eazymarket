<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Return-flow rework: returns no longer carry a restocking fee (only return
 * shipping, displayed on the refund page), and gain a return label URL (the
 * uploaded label file lives in a Spatie media collection).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            if (Schema::hasColumn('order_item_returns', 'restocking_fee')) {
                $table->dropColumn('restocking_fee');
            }
            if (!Schema::hasColumn('order_item_returns', 'return_label_url')) {
                $table->string('return_label_url', 512)->nullable()->after('return_tracking_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('order_item_returns', 'restocking_fee')) {
                $table->decimal('restocking_fee', 12, 2)->default(0)->after('refund_amount');
            }
            if (Schema::hasColumn('order_item_returns', 'return_label_url')) {
                $table->dropColumn('return_label_url');
            }
        });
    }
};
