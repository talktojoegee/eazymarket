<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_boys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('delivery_zone_id')->nullable();
            $table->string('full_name', 191)->nullable();
            $table->text('address')->nullable();
            $table->string('driver_license', 191)->nullable();
            $table->string('driver_license_number', 191)->nullable();
            $table->string('vehicle_type', 191)->nullable();
            $table->string('vehicle_registration', 191)->nullable();
            $table->enum('verification_status', ['pending', 'rejected', 'verified'])->default('pending');
            $table->text('verification_remark')->nullable();
            $table->unsignedInteger('drop_count')->default('0');
            $table->boolean('is_flagged')->default('0');
            $table->boolean('is_blocked')->default('0');
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason', 191)->nullable();
            $table->unsignedBigInteger('blocked_by_admin_id')->nullable();
            $table->string('referral_code', 32)->nullable();
            $table->string('friends_code', 32)->nullable();
            $table->enum('status', ['active', 'inactive']);
            $table->timestamps();
            $table->softDeletes();
            $table->unique('referral_code', 'delivery_boys_referral_code_unique');
            $table->index('delivery_zone_id', 'delivery_boys_delivery_zone_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_boys');
    }
};
