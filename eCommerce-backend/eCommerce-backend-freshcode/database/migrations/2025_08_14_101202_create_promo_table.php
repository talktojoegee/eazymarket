<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo', function (Blueprint $table) {
            $table->id();
            $table->string('code', 25);
            $table->text('description')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->enum('discount_type', ['free_shipping', 'flat', 'percent']);
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->enum('promo_mode', ['instant', 'cashback'])->default('instant');
            $table->enum('funded_by', ['PLATFORM', 'PRORATED_SELLERS'])->default('PLATFORM');
            $table->enum('scope', ['ORDER', 'SELLER', 'PRODUCT'])->default('ORDER');
            $table->integer('usage_count')->default('0');
            $table->integer('individual_use')->default('0');
            $table->integer('max_total_usage')->nullable();
            $table->integer('max_usage_per_user')->nullable();
            $table->decimal('min_order_total', 10, 2)->nullable();
            $table->decimal('max_discount_value', 10, 2)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique('code', 'promo_code_unique');
            $table->index(['scope', 'funded_by'], 'promo_scope_funded_by_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo');
    }
};
