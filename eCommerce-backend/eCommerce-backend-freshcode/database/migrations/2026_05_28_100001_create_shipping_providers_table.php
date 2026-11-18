<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32);
            $table->string('name', 80);
            $table->enum('type', ['carrier', 'manual', 'local_delivery', 'local_pickup']);
            $table->boolean('supports_awb')->default('0');
            $table->boolean('supports_tracking')->default('0');
            $table->boolean('supports_reverse_pickup')->default('0');
            $table->boolean('is_active')->default('1');
            $table->json('config')->nullable();
            $table->timestamps();
            $table->unique('code', 'shipping_providers_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_providers');
    }
};
