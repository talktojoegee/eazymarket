<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_uid', 191)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('referral_code', 32)->nullable();
            $table->string('friends_code', 32)->nullable();
            $table->timestamp('referral_prompt_dismissed_at')->nullable();
            $table->decimal('reward_points', 10, 2)->default('0.00');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('name', 191);
            $table->string('email', 191)->nullable();
            $table->string('country', 191)->nullable();
            $table->string('iso_2', 2)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->enum('logged_in_type', ['google', 'apple', 'platform'])->nullable();
            $table->enum('access_panel', ['web', 'admin', 'seller'])->default('web');
            $table->string('password', 191)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique('mobile', 'users_mobile_unique');
            $table->unique('email', 'users_email_unique');
            $table->unique('firebase_uid', 'users_firebase_uid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
