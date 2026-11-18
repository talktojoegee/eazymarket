<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('market_id')->nullable();
            $table->unsignedBigInteger('shipping_address_id')->nullable();
            $table->unsignedBigInteger('billing_address_id')->nullable();
            $table->timestamps();
            $table->unique('uuid', 'carts_uuid_unique');
            $table->index('market_id', 'carts_market_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
