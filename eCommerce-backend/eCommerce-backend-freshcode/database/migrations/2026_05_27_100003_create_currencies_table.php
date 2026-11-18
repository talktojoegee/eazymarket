<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3);
            $table->string('name', 64);
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('decimal_places')->default('2');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('is_base')->default('0');
            $table->timestamps();
            $table->primary('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
