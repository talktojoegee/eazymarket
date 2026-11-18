<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_product_section_field', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('custom_product_section_id');
            $table->unsignedBigInteger('custom_product_field_id');
            $table->unsignedInteger('sort_order')->default('0');
            $table->timestamps();
            $table->unique(['custom_product_section_id', 'custom_product_field_id'], 'custom_section_field_unique');
            $table->index(['custom_product_section_id', 'sort_order'], 'cpsf_section_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_product_section_field');
    }
};
