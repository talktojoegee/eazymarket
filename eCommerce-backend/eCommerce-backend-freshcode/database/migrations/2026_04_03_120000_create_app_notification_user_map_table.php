<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notification_user_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notification_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('user_type', ['customer', 'seller', 'rider']);
            $table->timestamps();
            $table->unique(['notification_id', 'user_id', 'user_type'], 'app_notification_user_map_unique');
            $table->index('user_id', 'app_notification_user_map_user_id_index');
            $table->index('user_type', 'app_notification_user_map_user_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notification_user_map');
    }
};
