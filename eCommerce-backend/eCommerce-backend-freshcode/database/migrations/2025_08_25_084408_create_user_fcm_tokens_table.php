<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('fcm_token', 191);
            $table->enum('device_type', ['android', 'ios', 'web'])->nullable();
            $table->enum('role_type', ['admin', 'seller', 'customer', 'rider'])->nullable();
            $table->timestamps();
            $table->unique('fcm_token', 'user_fcm_tokens_fcm_token_unique');
            $table->index('role_type', 'user_fcm_tokens_role_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_fcm_tokens');
    }
};
