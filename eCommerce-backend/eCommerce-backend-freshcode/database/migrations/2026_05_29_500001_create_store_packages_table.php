<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('name', 120);
            $table->enum('type', ['box', 'envelope', 'soft_pack'])->default('box');
            $table->unsignedInteger('length_cm')->default('0');
            $table->unsignedInteger('breadth_cm')->default('0');
            $table->unsignedInteger('height_cm')->default('0');
            $table->decimal('empty_weight_kg', 8, 3)->default('0.000');
            $table->boolean('is_default')->default('0');
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['store_id', 'name'], 'store_packages_store_name_uq');
            $table->index(['store_id', 'is_default'], 'store_packages_store_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_packages');
    }
};
