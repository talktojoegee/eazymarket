<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_parked_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('store_id');
            $table->string('label', 120)->nullable();
            $table->json('payload');
            $table->decimal('amount', 12, 2)->default('0.00');
            $table->timestamps();
            $table->index('seller_id', 'pos_parked_sales_seller_id_index');
            $table->index('store_id', 'pos_parked_sales_store_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_parked_sales');
    }
};
