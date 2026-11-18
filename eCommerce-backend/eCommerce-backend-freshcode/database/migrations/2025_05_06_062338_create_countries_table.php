<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->char('iso3', 3);
            $table->char('iso2', 2);
            $table->char('numeric_code', 3);
            $table->string('phonecode', 191);
            $table->string('capital', 191)->nullable();
            $table->string('currency', 3);
            $table->string('currency_name', 191)->nullable();
            $table->string('currency_symbol', 191)->nullable();
            $table->string('tld', 191)->nullable();
            $table->string('native', 191)->nullable();
            $table->string('region', 191)->nullable();
            $table->string('subregion', 191)->nullable();
            $table->json('timezones')->nullable();
            $table->json('translations')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('emoji', 191)->nullable();
            $table->string('emojiU', 191)->nullable();
            $table->boolean('flag')->nullable();
            $table->string('wikiDataId', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
