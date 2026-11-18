<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_country', function (Blueprint $table) {
            $table->unsignedBigInteger('market_id');
            $table->char('country_iso2', 2);
            $table->timestamps();
            $table->primary(['market_id', 'country_iso2']);
            $table->unique('country_iso2', 'market_country_country_iso2_unique');
            $table->index('country_iso2', 'market_country_country_iso2_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_country');
    }
};
