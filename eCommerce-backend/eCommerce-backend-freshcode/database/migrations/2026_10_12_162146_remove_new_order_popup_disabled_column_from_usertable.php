<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'new_order_popup_disabled')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('new_order_popup_disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usertable', function (Blueprint $table) {
            //
        });
    }
};
