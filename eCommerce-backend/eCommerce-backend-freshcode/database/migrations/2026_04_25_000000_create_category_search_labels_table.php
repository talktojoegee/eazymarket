<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_search_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->json('search_labels')->nullable();
            $table->timestamps();
            $table->unique('category_id', 'category_search_labels_category_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_search_labels');
    }
};
