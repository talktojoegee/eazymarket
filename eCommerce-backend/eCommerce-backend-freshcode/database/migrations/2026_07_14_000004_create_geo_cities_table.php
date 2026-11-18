<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_cities', function (Blueprint $table) {
            $table->id();
            $table->char('country_iso2', 2);
            $table->string('state_code', 16)->nullable();
            $table->string('name', 120);
            $table->timestamps();
            $table->index(['country_iso2', 'state_code'], 'geo_cities_country_state_idx');
            $table->index(['country_iso2', 'name'], 'geo_cities_country_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_cities');
    }
};
