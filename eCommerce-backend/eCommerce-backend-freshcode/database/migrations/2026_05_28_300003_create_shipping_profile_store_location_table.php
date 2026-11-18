<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_profile_store_location', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_profile_id');
            $table->unsignedBigInteger('store_location_id');
            $table->unsignedInteger('priority')->default('0');
            $table->timestamps();
            $table->unique(['shipping_profile_id', 'store_location_id'], 'spsl_profile_loc_unique');
            $table->index('store_location_id', 'spsl_loc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_profile_store_location');
    }
};
