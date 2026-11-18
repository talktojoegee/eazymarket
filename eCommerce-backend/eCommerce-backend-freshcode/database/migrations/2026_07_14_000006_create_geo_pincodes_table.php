<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_pincodes', function (Blueprint $table) {
            $table->id();
            $table->char('country_iso2', 2);
            $table->string('postcode', 16);
            $table->string('city', 120);
            $table->string('state_code', 16)->nullable();
            $table->string('state_name', 120)->nullable();
            $table->timestamps();
            $table->index(['country_iso2', 'postcode'], 'geo_pincodes_country_postcode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_pincodes');
    }
};
