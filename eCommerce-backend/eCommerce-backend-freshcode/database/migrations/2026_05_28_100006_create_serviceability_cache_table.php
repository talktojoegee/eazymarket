<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serviceability_cache', function (Blueprint $table) {
            $table->id();
            $table->string('provider_code', 32);
            $table->string('pickup_pin', 16);
            $table->string('delivery_pin', 16);
            $table->decimal('weight_kg', 8, 3)->default('0.000');
            $table->enum('payment_method', ['prepaid', 'cod'])->default('prepaid');
            $table->json('response_payload');
            $table->boolean('is_serviceable')->default('0');
            $table->decimal('cheapest_rate', 12, 2)->nullable();
            $table->unsignedInteger('fastest_eta_days')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['provider_code', 'pickup_pin', 'delivery_pin', 'weight_kg', 'payment_method'], 'serviceability_cache_key_unique');
            $table->index('expires_at', 'serviceability_cache_expires_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serviceability_cache');
    }
};
