<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('name', 120);
            $table->string('address_1', 191)->nullable();
            $table->string('address_2', 191)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->char('country_iso2', 2)->nullable();
            $table->string('country_name', 120)->nullable();
            $table->string('pin_code', 20)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('shiprocket_pickup_location_id', 64)->nullable();
            $table->boolean('is_registered_with_shiprocket')->default('0');
            $table->timestamp('shiprocket_registered_at')->nullable();
            $table->boolean('is_physical_storefront')->default('0');
            $table->boolean('fulfills_online_orders')->default('1');
            $table->boolean('offers_local_delivery')->default('0');
            $table->boolean('offers_local_pickup')->default('0');
            $table->boolean('offers_pos')->default('0');
            $table->boolean('is_active')->default('1');
            $table->boolean('is_default')->default('0');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['store_id', 'is_active'], 'store_locations_store_id_is_active_index');
            $table->index(['store_id', 'is_default'], 'store_locations_store_id_is_default_index');
            $table->index(['country_iso2', 'state', 'city'], 'store_locations_country_iso2_state_city_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_locations');
    }
};
