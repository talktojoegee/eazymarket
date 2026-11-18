<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_statements', function (Blueprint $table) {
            // Which admin user processed the settlement (audit trail). Nullable so
            // historical / system-settled rows are unaffected.
            $table->unsignedBigInteger('settled_by')->nullable()->after('settlement_reference');
            $table->index('settled_by', 'seller_statements_settled_by_index');
        });
    }

    public function down(): void
    {
        Schema::table('seller_statements', function (Blueprint $table) {
            $table->dropIndex('seller_statements_settled_by_index');
            $table->dropColumn('settled_by');
        });
    }
};
