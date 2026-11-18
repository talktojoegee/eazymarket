<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->integer('rating');
            $table->string('title', 191);
            $table->string('slug', 191);
            $table->string('description', 191);
            $table->timestamps();
            $table->unique('slug', 'seller_feedback_slug_unique');
            $table->unique('order_item_id', 'seller_feedback_order_item_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_feedback');
    }
};
