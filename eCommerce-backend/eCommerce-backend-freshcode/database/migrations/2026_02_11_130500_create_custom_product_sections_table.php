<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_product_sections', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('product_id');
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default('0');
            $table->timestamps();
            $table->softDeletes();
            $table->unique('uuid', 'custom_product_sections_uuid_unique');
            $table->index(['product_id', 'sort_order'], 'custom_product_sections_product_id_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_product_sections');
    }
};
