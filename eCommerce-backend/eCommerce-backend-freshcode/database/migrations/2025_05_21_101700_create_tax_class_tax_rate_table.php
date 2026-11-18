<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_class_tax_rate', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tax_class_id');
            $table->unsignedBigInteger('tax_rate_id');
            $table->timestamps();
            $table->unique(['tax_class_id', 'tax_rate_id'], 'tax_class_tax_rate_tax_class_id_tax_rate_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_class_tax_rate');
    }
};
