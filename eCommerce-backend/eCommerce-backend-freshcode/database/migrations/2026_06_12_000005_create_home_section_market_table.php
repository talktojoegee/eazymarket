<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_section_market', function (Blueprint $table) {
            $table->unsignedBigInteger('home_section_id');
            $table->unsignedBigInteger('market_id');
            $table->timestamps();

            $table->primary(['home_section_id', 'market_id']);
            $table->index('market_id', 'home_section_market_market_id_index');

            $table->foreign('home_section_id', 'home_section_market_section_id_foreign')
                ->references('id')->on('home_sections')->cascadeOnDelete();
            $table->foreign('market_id', 'home_section_market_market_id_foreign')
                ->references('id')->on('markets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_market');
    }
};
