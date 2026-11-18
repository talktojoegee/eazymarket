<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `orders.resolved_at` — the moment an order first became fully resolved (all
 * lines delivered/terminal with no open return). It drives the auto-archive grace
 * clock: an order is archived (and, later, settled) only once
 * now >= max(latest return_deadline, resolved_at + auto-archive grace).
 *
 * Set/cleared by FulfillmentRollupService::recomputeOrderStatus; it resets to null
 * whenever the order reopens (e.g. a new return), so the grace restarts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('status');
                $table->index('resolved_at', 'orders_resolved_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'resolved_at')) {
                $table->dropIndex('orders_resolved_at_index');
                $table->dropColumn('resolved_at');
            }
        });
    }
};
