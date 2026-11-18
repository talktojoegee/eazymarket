<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_market', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('market_id');
            $table->timestamps();
            $table->unique('user_id', 'user_market_user_id_unique');
            $table->index('market_id', 'user_market_market_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_market');
    }
};
