<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_item_addons', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('cart_item_id');
            $table->unsignedBigInteger('addon_group_id');
            $table->unsignedBigInteger('addon_item_id');
            $table->decimal('price', 10, 2);
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique('uuid', 'cart_item_addons_uuid_unique');
            $table->index('cart_item_id', 'cart_item_addons_cart_item_id_index');
            $table->index(['addon_group_id', 'addon_item_id'], 'cart_item_addons_addon_group_id_addon_item_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_item_addons');
    }
};
