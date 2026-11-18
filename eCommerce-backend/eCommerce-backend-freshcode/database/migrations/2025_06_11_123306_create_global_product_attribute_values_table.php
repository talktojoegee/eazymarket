<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('global_attribute_id');
            $table->string('title', 191);
            $table->text('swatche_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_product_attribute_values');
    }
};
