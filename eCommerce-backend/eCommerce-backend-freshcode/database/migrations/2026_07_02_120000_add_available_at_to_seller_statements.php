<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maturity date for a pending settlement entry. The auto-settle cron only releases
 * an entry into the seller wallet once `available_at <= now`. Set at creation to
 * `posted_at + settlementClearingDays`; a null value means "available immediately"
 * (0-day clearing / pre-existing rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('seller_statements', 'available_at')) {
            Schema::table('seller_statements', function (Blueprint $table) {
                $table->timestamp('available_at')->nullable()->after('posted_at');
            });
        }

        $indexExists = DB::getDriverName() === 'mysql'
            ? collect(DB::select("SHOW INDEX FROM seller_statements WHERE Key_name = 'seller_statements_auto_settle_index'"))->isNotEmpty()
            : Schema::hasIndex('seller_statements', 'seller_statements_auto_settle_index');
        if (! $indexExists) {
            Schema::table('seller_statements', function (Blueprint $table) {
                $table->index(['settlement_status', 'on_hold', 'available_at'], 'seller_statements_auto_settle_index');
            });
        }

        // Existing pending entries were created before clearing existed — treat them
        // as already matured (available at their posted_at).
        DB::table('seller_statements')
            ->whereNull('available_at')
            ->update(['available_at' => DB::raw('posted_at')]);
    }

    public function down(): void
    {
        Schema::table('seller_statements', function (Blueprint $table) {
            $table->dropIndex('seller_statements_auto_settle_index');
            $table->dropColumn('available_at');
        });
    }
};
