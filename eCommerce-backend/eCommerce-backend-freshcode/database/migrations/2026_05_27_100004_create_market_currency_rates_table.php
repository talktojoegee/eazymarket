<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_currency_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_id');
            $table->char('from_currency', 3);
            $table->char('to_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->unique(['market_id', 'from_currency', 'to_currency', 'valid_from'], 'market_currency_rates_unique');
            $table->index(['from_currency', 'to_currency'], 'market_currency_rates_from_currency_to_currency_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_currency_rates');
    }
};
