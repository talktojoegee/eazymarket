<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_promo_line', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('promo_id');
            $table->string('promo_code', 25);
            $table->decimal('discount_amount', 10, 2);
            $table->boolean('cashback_flag')->default('0');
            $table->boolean('is_awarded')->default('0');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_promo_line');
    }
};
