<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->enum('key', ['store_limit', 'product_limit', 'role_limit', 'system_user_limit', 'variation_product_limit', 'pos_access']);
            $table->integer('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_limits');
    }
};
