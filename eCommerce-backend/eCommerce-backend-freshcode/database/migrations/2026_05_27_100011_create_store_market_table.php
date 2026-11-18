<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_market', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('market_id');
            $table->timestamps();
            $table->primary(['store_id', 'market_id']);
            $table->index('market_id', 'store_market_market_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_market');
    }
};
