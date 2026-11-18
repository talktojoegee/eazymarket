<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('plan_id');
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending'])->default('active');
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->decimal('price_paid', 10, 2)->default('0.00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_subscriptions');
    }
};
