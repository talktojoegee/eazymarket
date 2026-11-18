<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_product_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('title', 191);
            $table->string('slug', 350);
            $table->string('label', 191);
            $table->enum('swatche_type', ['text', 'color', 'image'])->default('text');
            $table->softDeletes();
            $table->timestamps();
            $table->unique('slug', 'global_product_attributes_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_product_attributes');
    }
};
