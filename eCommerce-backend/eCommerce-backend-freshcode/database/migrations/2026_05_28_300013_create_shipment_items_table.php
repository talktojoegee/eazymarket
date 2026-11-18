<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['shipment_id', 'order_item_id'], 'shipment_items_shipment_id_order_item_id_unique');
            $table->index('order_item_id', 'shipment_items_order_item_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
