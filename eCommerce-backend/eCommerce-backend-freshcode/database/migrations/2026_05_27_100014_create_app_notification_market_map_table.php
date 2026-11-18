<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notification_market_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notification_id');
            $table->unsignedBigInteger('market_id');
            $table->timestamps();
            $table->unique(['notification_id', 'market_id'], 'app_notification_market_map_unique');
            $table->index('market_id', 'app_notification_market_map_market_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notification_market_map');
    }
};
