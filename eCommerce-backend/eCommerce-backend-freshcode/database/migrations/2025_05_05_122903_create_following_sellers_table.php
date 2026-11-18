<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('following_sellers', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 191);
            $table->string('seller_id', 191);
            $table->unsignedBigInteger('user_seller_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('following_sellers');
    }
};
