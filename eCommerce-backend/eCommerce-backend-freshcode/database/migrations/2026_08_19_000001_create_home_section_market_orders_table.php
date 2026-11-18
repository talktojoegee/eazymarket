<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_section_market_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('home_section_id');
            $table->unsignedBigInteger('market_id');
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['home_section_id', 'market_id'], 'home_section_market_orders_section_market_unique');
            $table->index(['market_id', 'sort_order'], 'home_section_market_orders_market_sort_index');

            $table->foreign('home_section_id', 'home_section_market_orders_section_foreign')
                ->references('id')->on('home_sections')->cascadeOnDelete();
            $table->foreign('market_id', 'home_section_market_orders_market_foreign')
                ->references('id')->on('markets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_market_orders');
    }
};
