<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_conditions', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('category_id');
            $table->string('title', 191);
            $table->string('slug', 191);
            $table->enum('alignment', ['strip']);
            $table->timestamps();
            $table->unique('uuid', 'product_conditions_uuid_unique');
            $table->unique('slug', 'product_conditions_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_conditions');
    }
};
