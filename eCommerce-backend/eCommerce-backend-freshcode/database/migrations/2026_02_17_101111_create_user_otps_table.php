<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_otps', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 191);
            $table->string('otp', 191);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default('0');
            $table->timestamps();
            $table->index(['mobile', 'expires_at'], 'user_otps_mobile_expires_at_index');
            $table->index('mobile', 'user_otps_mobile_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_otps');
    }
};
