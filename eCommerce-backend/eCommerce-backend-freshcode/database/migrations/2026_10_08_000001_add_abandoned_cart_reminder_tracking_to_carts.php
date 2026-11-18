<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->timestamp('abandoned_cart_reminded_for')->nullable()->index();
            $table->timestamp('abandoned_cart_next_reminder_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['abandoned_cart_reminded_for']);
            $table->dropIndex(['abandoned_cart_next_reminder_at']);
            $table->dropColumn(['abandoned_cart_reminded_for', 'abandoned_cart_next_reminder_at']);
        });
    }
};
