<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_fcm_tokens', function (Blueprint $table) {
            $table->string('fcm_token', 512)->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_fcm_tokens', function (Blueprint $table) {
            $table->string('fcm_token', 191)->change();
        });
    }
};
