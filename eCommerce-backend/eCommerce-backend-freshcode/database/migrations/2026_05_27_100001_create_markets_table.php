<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8);
            $table->string('name', 64);
            $table->char('currency_code', 3);
            $table->enum('symbol_position', ['before', 'after'])->default('before');
            $table->boolean('space_between_symbol')->default('0');
            $table->string('thousand_separator', 3)->default('');
            $table->string('decimal_separator', 3)->default('.');
            $table->enum('grouping_style', ['western', 'indian'])->default('western');
            $table->unsignedTinyInteger('decimal_places')->nullable();
            $table->string('negative_format', 16)->default('-{n}');
            $table->string('default_language', 5)->default('en');
            $table->boolean('is_default')->default('0');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('priority')->default('0');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('code', 'markets_code_unique');
            $table->index(['status', 'priority'], 'markets_status_priority_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};
