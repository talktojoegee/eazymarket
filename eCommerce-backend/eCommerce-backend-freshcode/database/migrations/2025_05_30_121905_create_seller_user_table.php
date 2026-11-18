<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('seller_id');
            $table->timestamps();
            $table->unique(['user_id', 'seller_id'], 'unique_seller_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_user');
    }
};
