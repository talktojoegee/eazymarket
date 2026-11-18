<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('store_id');
            $table->integer('rating');
            $table->string('title', 191);
            $table->string('slug', 191);
            $table->text('comment');
            $table->timestamps();
            $table->unique('slug', 'reviews_slug_unique');
            $table->unique('order_item_id', 'reviews_order_item_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
