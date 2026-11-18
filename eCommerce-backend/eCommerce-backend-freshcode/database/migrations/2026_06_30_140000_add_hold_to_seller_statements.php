<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_statements', function (Blueprint $table) {
            $table->boolean('on_hold')->default(false)->after('settled_by');
            $table->string('hold_reason', 191)->nullable()->after('on_hold');
            $table->unsignedBigInteger('held_by')->nullable()->after('hold_reason');
            $table->timestamp('held_at')->nullable()->after('held_by');
            $table->index(['settlement_status', 'on_hold'], 'seller_statements_status_hold_index');
        });
    }

    public function down(): void
    {
        Schema::table('seller_statements', function (Blueprint $table) {
            $table->dropIndex('seller_statements_status_hold_index');
            $table->dropColumn(['on_hold', 'hold_reason', 'held_by', 'held_at']);
        });
    }
};
