<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_subscription_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_subscription_id');
            $table->string('plan_name', 191);
            $table->decimal('price', 10, 2);
            $table->integer('duration_days')->nullable();
            $table->json('limits_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_subscription_snapshots');
    }
};
