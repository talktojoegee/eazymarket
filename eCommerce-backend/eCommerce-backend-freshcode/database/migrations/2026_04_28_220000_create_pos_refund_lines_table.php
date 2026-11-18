<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_refund_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_refund_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 12, 2)->default('0.00');
            $table->timestamps();
            $table->index('order_item_id', 'pos_refund_lines_order_item_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_refund_lines');
    }
};
