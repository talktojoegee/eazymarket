<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('product_id');
            $table->string('title', 191);
            $table->string('slug', 500);
            $table->double('weight')->nullable();
            $table->double('height')->nullable();
            $table->double('breadth')->nullable();
            $table->double('length')->nullable();
            $table->boolean('availability');
            $table->string('provider', 191)->default('self');
            $table->string('provider_product_id', 191)->nullable();
            $table->json('provider_json')->nullable();
            $table->string('barcode', 191);
            $table->string('sku', 100)->nullable();
            $table->decimal('price', 12, 2)->default('0.00');
            $table->decimal('special_price', 12, 2)->default('0.00');
            $table->decimal('cost', 12, 2)->nullable();
            $table->enum('visibility', ['published', 'draft']);
            $table->boolean('is_default');
            $table->softDeletes();
            $table->timestamps();
            $table->unique('slug', 'product_variants_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
