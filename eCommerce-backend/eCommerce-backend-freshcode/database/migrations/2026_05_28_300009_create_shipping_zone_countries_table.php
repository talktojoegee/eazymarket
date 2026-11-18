<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zone_countries', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_zone_id');
            $table->char('country_iso2', 2);
            $table->timestamps();
            $table->primary(['shipping_zone_id', 'country_iso2']);
            $table->index('country_iso2', 'shipping_zone_countries_country_iso2_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_countries');
    }
};
