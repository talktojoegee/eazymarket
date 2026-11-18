<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shipping_zone_cities');
        Schema::dropIfExists('shipping_zone_states');
    }

    public function down(): void
    {
        Schema::create('shipping_zone_states', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_zone_id');
            $table->char('country_iso2', 2);
            $table->string('state_code', 16);
            $table->string('state_name', 120);
            $table->timestamps();
            $table->primary(['shipping_zone_id', 'country_iso2', 'state_code']);
            $table->index(['country_iso2', 'state_code'], 'shipping_zone_states_country_iso2_state_code_index');
        });

        Schema::create('shipping_zone_cities', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_zone_id');
            $table->char('country_iso2', 2);
            $table->string('state_code', 16);
            $table->string('city_key', 120);
            $table->string('city_name', 120);
            $table->timestamps();
            $table->primary(['shipping_zone_id', 'country_iso2', 'state_code', 'city_key']);
            $table->index(['country_iso2', 'state_code', 'city_key'], 'szci_lookup_idx');
        });
    }
};
