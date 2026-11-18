<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_states', function (Blueprint $table) {
            $table->id();
            $table->char('country_iso2', 2);
            $table->string('state_code', 16)->nullable();
            $table->string('name', 120);
            $table->timestamps();
            $table->index('country_iso2', 'geo_states_country_idx');
            $table->index(['country_iso2', 'state_code'], 'geo_states_country_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_states');
    }
};
