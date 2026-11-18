<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('referred_id');
            $table->string('referral_code', 32);
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->json('settings')->nullable();
            $table->unique('referred_id', 'referrals_referred_id_unique');
            $table->index('referrer_id', 'referrals_referrer_id_index');
            $table->index('status', 'referrals_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
